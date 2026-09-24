<?php

namespace App\Services;

use App\Models\DaftarMobil;
use App\Models\Jadwal;
use App\Models\MasterDriver;
use App\Models\MobilisasiOperasional;
use App\Models\MobilisasiOperasionalDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Exception;

/**
 * Assignment mobil + driver untuk Jadwal Tim.
 *
 * Sumber baru: tabel mobilisasi_operasional (+ detail by id_sampling + anggota sampler).
 * id_jadwal pada detail hanya snapshot pointer ke baris jadwal aktif (di-update saat sync).
 * Kolom lama jadwal.kendaraan / jadwal.driver tetap diisi dulu (dual-write)
 * supaya fitur lama sejak ~2020 tidak putus.
 *
 * Rencana pensiun kolom jadwal.kendaraan (pelan-pelan, jangan sekaligus):
 * 1. Set DUAL_WRITE_KENDARAAN = false  → berhenti menulis jadwal.kendaraan
 * 2. Pindahkan pembaca jadwal.kendaraan (dokumen MO, Jadwal Tim, dll) ke tabel ini
 * 3. Drop kolom jadwal.kendaraan setelah semua pembaca pindah
 * 4. DUAL_WRITE_DRIVER bisa dimatikan belakangan jika driver juga pindah penuh ke MO
 *
 * Tab Belum = jadwal tanggal itu yang kendaraan masih kosong,
 * dan belum masuk tabel MO. Driver kosong tidak membuatnya "belum diatur".
 */
class MobilisasiOperasionalService
{
    /** @var bool|null */
    private static $moTablesReadyCache = null;

    /** @var bool|null */
    private static $detailSamplingColumnsCache = null;

    public const DUAL_WRITE_KENDARAAN = true;
    public const DUAL_WRITE_DRIVER = true;

    /** jadwal_columns = kendaraan masih kosong. mo_table = hanya cek tabel MO. */
    public const UNASSIGNED_MODE = 'jadwal_columns';

    public function listBelumDiatur($tanggal)
    {
        $query = $this->baseJadwalQuery($tanggal);
        $this->applyUnassignedFilter($query);

        return $this->groupJadwalAsTim($query->get());
    }

    public function listSudahDiatur($tanggal)
    {
        $rows = MobilisasiOperasional::with([
            'details.jadwal' => function ($q) {
                $q->with([
                    'quotationKontrakH' => function ($qq) {
                        $qq->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                            ->where('is_active', true);
                    },
                    'quotationNonKontrak' => function ($qq) {
                        $qq->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                            ->where('is_active', true);
                    },
                ]);
            },
        ])
            ->where('is_active', true)
            ->where('tanggal_sampling', $tanggal)
            ->orderBy('jam_keberangkatan')
            ->orderBy('id')
            ->get();

        $timList = collect();
        foreach ($rows as $mo) {
            $jadwalRows = $mo->details
                ->map(function ($detail) use ($mo) {
                    return $this->resolveJadwalForDetail($detail, $mo->tanggal_sampling);
                })
                ->filter()
                ->values();

            if ($jadwalRows->isEmpty()) {
                continue;
            }

            $timGroups = $this->groupJadwalAsTim($jadwalRows, $mo->nama_driver);
            foreach ($timGroups as $tim) {
                $timList->push(array_merge($tim, [
                    'id_mo' => $mo->id,
                    'plat_mobil' => $mo->plat_mobil,
                    'nama_driver' => $mo->nama_driver,
                    'jam_keberangkatan' => $mo->jam_keberangkatan
                        ? substr($mo->jam_keberangkatan, 0, 5)
                        : null,
                    'durasi_mo' => $mo->durasi,
                    'tanggal_pulang' => $mo->tanggal_pulang,
                    'catatan' => $mo->catatan,
                ]));
            }
        }

        return $timList->values();
    }

    public function show($id)
    {
        $mo = MobilisasiOperasional::with(['details.jadwal'])
            ->where('is_active', true)
            ->where('id', $id)
            ->first();

        if (!$mo) {
            throw new Exception('Mobilisasi operasional tidak ditemukan', 404);
        }

        return $this->presentMo($mo);
    }

    public function getOptions($tanggal, $excludeMoId = null, $durasi = null)
    {
        $driver = MasterDriver::where('is_active', true)
            ->orderBy('nama_driver')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->nama_driver,
                    'text' => $item->nama_driver,
                    'user_id' => $item->user_id,
                ];
            });

        $kendaraan = $this->fetchKendaraanAvailable($tanggal, $excludeMoId, $durasi);

        $timQuery = $this->baseJadwalQuery($tanggal);
        $this->applyUnassignedFilter($timQuery, $excludeMoId);
        $tim = $this->groupJadwalAsTim($timQuery->get());

        if ($excludeMoId) {
            $current = MobilisasiOperasional::with(['details.jadwal'])
                ->where('is_active', true)
                ->where('id', $excludeMoId)
                ->first();
            if ($current) {
                $currentTim = $this->groupJadwalAsTim(
                    $current->details
                        ->map(function ($detail) use ($current) {
                            $resolved = $this->resolveJadwalForDetail($detail, $current->tanggal_sampling);
                            if ($resolved) {
                                return $resolved;
                            }

                            return (object) [
                                'id' => $detail->id_jadwal,
                                'sampler' => $detail->sampler,
                                'no_quotation' => $detail->no_quotation,
                                'nama_perusahaan' => $detail->nama_perusahaan,
                                'wilayah' => $detail->wilayah,
                                'jam_mulai' => $detail->jam_mulai,
                                'jam_selesai' => $detail->jam_selesai,
                                'durasi' => $detail->durasi,
                                'periode' => null,
                                'note' => null,
                                'kategori' => null,
                                'driver' => $current->nama_driver,
                                'kendaraan' => $current->plat_mobil,
                                'parsial' => null,
                                'id_cabang' => null,
                                'quotationKontrakH' => null,
                                'quotationNonKontrak' => null,
                            ];
                        })
                        ->values(),
                    $current->nama_driver
                );
                $existingKeys = $tim->pluck('tim_key');
                foreach ($currentTim as $row) {
                    if (!$existingKeys->contains($row['tim_key'])) {
                        $tim->push($row);
                    }
                }
            }
        }

        return [
            'mobil' => $kendaraan['data'],
            'driver' => $driver,
            'tim' => $tim->values(),
            'conflicts' => $kendaraan['conflicts'],
        ];
    }

    public function save(array $payload, $actor)
    {
        $idJadwal = $this->normalizeIds($payload['id_jadwal'] ?? []);
        if (count($idJadwal) === 0) {
            throw new Exception('Pilih minimal satu tim.', 422);
        }

        $jadwalRows = Jadwal::whereIn('id', $idJadwal)
            ->where('is_active', true)
            ->get();

        if ($jadwalRows->count() !== count($idJadwal)) {
            throw new Exception('Ada jadwal yang tidak aktif atau tidak ditemukan.', 422);
        }

        $tanggalSampling = $jadwalRows->pluck('tanggal')->unique()->filter()->values();
        if ($tanggalSampling->count() !== 1) {
            throw new Exception('Semua tim dalam satu MO harus tanggal sampling yang sama.', 422);
        }

        $tanggal = Carbon::parse($tanggalSampling->first())->toDateString();
        $durasi = (int) $jadwalRows->max('durasi');
        $tanggalPulang = $this->hitungTanggalPulang($tanggal, $durasi);

        $kendaraan = $this->resolveKendaraan($payload);
        $plat = trim((string) $kendaraan->plat_mobil);
        if ($plat === '') {
            throw new Exception('Mobil wajib dipilih.', 422);
        }

        $namaDriver = trim((string) ($payload['nama_driver'] ?? $payload['id_driver'] ?? ''));
        $driver = MasterDriver::where('is_active', true)
            ->where('nama_driver', $namaDriver)
            ->first();
        if (!$driver) {
            throw new Exception('Driver tidak ditemukan.', 422);
        }

        $moId = !empty($payload['id']) ? (int) $payload['id'] : null;
        $this->assertJadwalAvailable($idJadwal, $moId);
        $this->assertMobilAvailable($kendaraan->id, $plat, $tanggal, $tanggalPulang, $moId);

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $jamKeberangkatan = $payload['jam_keberangkatan'] ?: '06:00';

        DB::beginTransaction();
        try {
            if ($moId) {
                $header = MobilisasiOperasional::where('id', $moId)
                    ->where('is_active', true)
                    ->first();
                if (!$header) {
                    throw new Exception('Mobilisasi operasional tidak ditemukan', 404);
                }

                $oldIds = $header->details()->where('is_active', true)->pluck('id_jadwal')->all();
                $this->clearLegacyColumns($oldIds);

                $header->id_mobil = $kendaraan->id;
                $header->plat_mobil = $plat;
                $header->id_driver = $driver->user_id;
                $header->nama_driver = $driver->nama_driver;
                $header->tanggal_sampling = $tanggal;
                $header->tanggal_pulang = $tanggalPulang;
                $header->jam_keberangkatan = $jamKeberangkatan;
                $header->durasi = $durasi;
                $header->catatan = $payload['catatan'] ?? null;
                $header->updated_at = $now;
                $header->updated_by = $actor;
                $header->save();
            } else {
                $header = MobilisasiOperasional::create([
                    'id_mobil' => $kendaraan->id,
                    'plat_mobil' => $plat,
                    'id_driver' => $driver->user_id,
                    'nama_driver' => $driver->nama_driver,
                    'tanggal_sampling' => $tanggal,
                    'tanggal_pulang' => $tanggalPulang,
                    'jam_keberangkatan' => $jamKeberangkatan,
                    'durasi' => $durasi,
                    'catatan' => $payload['catatan'] ?? null,
                    'created_at' => $now,
                    'created_by' => $actor,
                    'is_active' => true,
                ]);
            }

            $this->syncMoHeaderFromJadwalRows(
                $header,
                $jadwalRows,
                $actor,
                $now,
                $moId ? 'Edit atur mobilisasi operasional' : 'Atur mobilisasi operasional',
                'atur_mo',
                true
            );

            DB::commit();

            return $this->show($header->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function delete($id, $actor)
    {
        $header = MobilisasiOperasional::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (!$header) {
            throw new Exception('Mobilisasi operasional tidak ditemukan', 404);
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $jadwalIds = $header->details()->pluck('id_jadwal')->all();

        DB::beginTransaction();
        try {
            $this->clearLegacyColumns($jadwalIds);

            MobilisasiOperasionalDetail::where('id_mo', $header->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deleted_at' => $now,
                    'deleted_by' => $actor,
                ]);

            $header->is_active = false;
            $header->deleted_at = $now;
            $header->deleted_by = $actor;
            $header->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Setelah jadwal tim diubah (induk maupun parsial yang sudah punya MO):
     * penugasan MO tetap aktif di tab Sudah; detail MO di-remap ke id jadwal baru.
     * Jadwal parsial yang belum pernah MO diatur lewat prepareNewParsialJadwalForMo saat insert.
     */
    public function reconcileAfterJadwalChange(
        array $oldIds,
        array $newIds,
        $actor = null,
        $alasan = null,
        $oldJadwalBefore = null,
        array $context = []
    ) {
        if (!$this->tablesReady()) {
            return;
        }

        $oldIds = $this->normalizeIds($oldIds);
        $newIds = $this->normalizeIds($newIds);
        if (count($oldIds) === 0) {
            return;
        }

        $actor = $actor ?: 'System';
        $alasan = $alasan ?: 'Update jadwal sampling plan';

        if (!$this->hasMoAffectedByJadwalReplace($oldIds, $newIds)) {
            return;
        }

        $this->remapAfterJadwalReplace($oldIds, $newIds, $actor, $alasan);
    }

    /**
     * Cek ringan: apakah ada MO aktif yang terkait id jadwal lama (atau id_sampling-nya).
     * Dipakai sebelum sync penuh agar update jadwal tanpa MO tidak kena query berat.
     */
    public function hasMoAffectedByJadwalReplace(array $oldIds, array $newIds = [])
    {
        if (!$this->tablesReady()) {
            return false;
        }

        $oldIds = $this->normalizeIds($oldIds);
        if (count($oldIds) === 0) {
            return false;
        }

        if (MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_jadwal', $oldIds)
            ->exists()) {
            return true;
        }

        if (!$this->detailHasSamplingColumns()) {
            return false;
        }

        $touchIds = array_values(array_unique(array_merge(
            $oldIds,
            $this->normalizeIds($newIds)
        )));

        $samplingIds = Jadwal::whereIn('id', $touchIds)
            ->whereNotNull('id_sampling')
            ->distinct()
            ->pluck('id_sampling');

        if ($samplingIds->isEmpty()) {
            return false;
        }

        return MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_sampling', $samplingIds->all())
            ->exists();
    }

    /** Baris jadwal parsial baru: tidak mewarisi mobil/driver induk → tab Belum Diatur MO. */
    public function prepareNewParsialJadwalForMo(array $jadwalIds, $actor = null)
    {
        $ids = $this->normalizeIds($jadwalIds);
        if (!$this->tablesReady() || count($ids) === 0) {
            return;
        }

        $this->clearLegacyColumns($ids);
    }

    public function remapAfterJadwalReplace(array $oldIds, array $newIds, $actor = null, $alasan = null)
    {
        if (!$this->tablesReady()) {
            return;
        }

        $oldIds = $this->normalizeIds($oldIds);
        $newIds = $this->normalizeIds($newIds);
        if (count($oldIds) === 0 && count($newIds) === 0) {
            return;
        }

        $actor = $actor ?: 'System';
        $alasan = $alasan ?: 'Update jadwal sampling plan';
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $touchIds = array_values(array_unique(array_merge($oldIds, $newIds)));
        $touchRows = Jadwal::whereIn('id', $touchIds)->get();
        $samplingIds = $touchRows->pluck('id_sampling')->filter()->unique()->values();

        $detailsQuery = MobilisasiOperasionalDetail::where('is_active', true);
        $detailsQuery->where(function ($query) use ($oldIds, $samplingIds) {
            if (count($oldIds) > 0) {
                $query->whereIn('id_jadwal', $oldIds);
            }
            if ($this->detailHasSamplingColumns() && $samplingIds->isNotEmpty()) {
                $method = count($oldIds) > 0 ? 'orWhereIn' : 'whereIn';
                $query->{$method}('id_sampling', $samplingIds->all());
            }
        });

        $details = $detailsQuery->get();
        if ($details->isEmpty()) {
            return;
        }

        foreach ($details->groupBy('id_mo') as $moId => $moDetails) {
            $header = MobilisasiOperasional::where('id', $moId)
                ->where('is_active', true)
                ->first();
            if (!$header) {
                $this->deactivateDetails($moDetails, $actor, $now, $alasan, 'update_jadwal');
                continue;
            }

            $visitScopes = $this->collectVisitScopesFromDetails($moDetails);
            $jadwalRows = $this->loadActiveJadwalForVisitScopes($visitScopes, $header->tanggal_sampling);

            if ($jadwalRows->isEmpty()) {
                $this->deactivateDetails($moDetails, $actor, $now, $alasan, 'update_jadwal');
                continue;
            }

            $this->syncMoHeaderFromJadwalRows(
                $header,
                $jadwalRows,
                $actor,
                $now,
                $alasan,
                'update_jadwal',
                true
            );

            Log::channel('sampling')->info('MO sync setelah update jadwal (id_sampling)', [
                'id_mo' => $header->id,
                'actor' => $actor,
                'alasan' => $alasan,
                'old_ids' => $oldIds,
                'new_ids' => $newIds,
                'id_sampling' => $samplingIds->all(),
            ]);
        }
    }

    public function syncSnapshots(array $jadwalIds, $actor = null, $alasan = null)
    {
        $this->remapAfterJadwalReplace(
            $this->normalizeIds($jadwalIds),
            $this->normalizeIds($jadwalIds),
            $actor,
            $alasan
        );
    }

    public function hitungTanggalPulang($tanggalSampling, $durasi)
    {
        $durasi = (int) $durasi;
        $addDays = $durasi <= 1 ? 0 : ($durasi - 1);

        return Carbon::parse($tanggalSampling)->addDays($addDays)->toDateString();
    }

    protected function applyLegacyColumns(array $jadwalIds, $plat, $namaDriver)
    {
        $jadwalIds = $this->normalizeIds($jadwalIds);
        if (count($jadwalIds) === 0) {
            return;
        }

        $payload = [];
        if (self::DUAL_WRITE_DRIVER) {
            $payload['driver'] = $namaDriver;
        }
        if (self::DUAL_WRITE_KENDARAAN) {
            $payload['kendaraan'] = $plat;
        }

        if (count($payload) === 0) {
            return;
        }

        Jadwal::whereIn('id', $jadwalIds)->update($payload);
    }

    protected function clearLegacyColumns(array $jadwalIds)
    {
        $jadwalIds = $this->normalizeIds($jadwalIds);
        if (count($jadwalIds) === 0) {
            return;
        }

        $payload = [];
        if (self::DUAL_WRITE_DRIVER) {
            $payload['driver'] = null;
        }
        if (self::DUAL_WRITE_KENDARAAN) {
            $payload['kendaraan'] = null;
        }

        if (count($payload) === 0) {
            return;
        }

        Jadwal::whereIn('id', $jadwalIds)->update($payload);
    }

    protected function applyUnassignedFilter($query, $excludeMoId = null)
    {
        if ($this->tablesReady()) {
            if ($this->detailHasSamplingColumns()) {
                $query->whereNotExists(function ($sub) use ($excludeMoId) {
                    $sub->select(DB::raw(1))
                        ->from('mobilisasi_operasional_detail as d')
                        ->whereColumn('d.id_jadwal', 'jadwal.id')
                        ->where('d.is_active', true);

                    if ($excludeMoId) {
                        $sub->where('d.id_mo', '!=', $excludeMoId);
                    }
                })->whereNotExists(function ($sub) use ($excludeMoId) {
                    $sub->select(DB::raw(1))
                        ->from('mobilisasi_operasional_detail as d')
                        ->where('d.is_active', true)
                        ->whereNotNull('d.id_sampling')
                        ->whereColumn('d.id_sampling', 'jadwal.id_sampling')
                        ->where(function ($match) {
                            $match->where(function ($withUser) {
                                $withUser->whereNotNull('jadwal.userid')
                                    ->whereColumn('d.userid', 'jadwal.userid');
                            })->orWhere(function ($withName) {
                                $withName->whereNull('jadwal.userid')
                                    ->whereColumn('d.sampler', 'jadwal.sampler');
                            });
                        })
                        ->where(function ($parsialMatch) {
                            $parsialMatch->where(function ($bothNull) {
                                $bothNull->whereNull('d.parsial')->whereNull('jadwal.parsial');
                            })->orWhereColumn('d.parsial', 'jadwal.parsial');
                        });

                    if ($excludeMoId) {
                        $sub->where('d.id_mo', '!=', $excludeMoId);
                    }
                });
            } else {
                $query->whereNotIn('id', function ($sub) use ($excludeMoId) {
                    $sub->select('id_jadwal')
                        ->from('mobilisasi_operasional_detail')
                        ->where('is_active', true)
                        ->whereNotNull('id_jadwal');

                    if ($excludeMoId) {
                        $sub->where('id_mo', '!=', $excludeMoId);
                    }
                });
            }
        }

        if (self::UNASSIGNED_MODE === 'jadwal_columns') {
            $query->where(function ($q) {
                $q->whereNull('kendaraan')->orWhereRaw("TRIM(kendaraan) = ''");
            });
        }

        return $query;
    }

    protected function baseJadwalQuery($tanggal)
    {
        return Jadwal::with([
            'quotationKontrakH' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                    ->where('is_active', true);
            },
            'quotationNonKontrak' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                    ->where('is_active', true);
            },
        ])
            ->whereNotNull('no_quotation')
            ->where('is_active', true)
            ->where('tanggal', $tanggal)
            ->orderBy('jam_mulai');
    }

    protected function groupJadwalAsTim($rows, $driverName = null)
    {
        return collect($rows)
            ->map(function ($item) {
                $quotation = strpos($item->no_quotation, 'QTC') !== false
                    ? $item->quotationKontrakH
                    : $item->quotationNonKontrak;

                $item->pic = $quotation ? [
                    'nama_pic_sampling' => $quotation->nama_pic_sampling,
                    'no_tlp_pic_sampling' => $quotation->no_tlp_pic_sampling,
                ] : null;

                return $item;
            })
            ->groupBy(function ($item) {
                return implode('|', [
                    $item->no_quotation,
                    $item->kendaraan,
                    $item->parsial,
                    $item->periode,
                    $item->nama_perusahaan,
                    $item->durasi,
                    $item->jam_mulai,
                    $item->jam_selesai,
                    $item->wilayah,
                    $item->id_cabang,
                    $item->note,
                ]);
            })
            ->map(function ($group) use ($driverName) {
                $first = $group->first();
                $ids = $group->pluck('id')->map(function ($id) {
                    return (int) $id;
                })->unique()->values();

                $kategoriItems = $group->flatMap(function ($item) {
                    return $this->parseKategori($item->kategori ?? null);
                })->unique()->values()->all();

                $teamSamplers = $group->pluck('sampler')
                    ->map(function ($s) {
                        return trim($s);
                    })
                    ->filter()
                    ->unique()
                    ->values();

                $labelDriver = $driverName ?: $first->driver;
                $normalizedTeam = $teamSamplers->sort()->values()->implode(', ');
                $displaySampler = $teamSamplers->map(function ($sampler) use ($labelDriver) {
                    if ($this->isSamePerson($sampler, $labelDriver)) {
                        return $sampler . ' (driver)';
                    }
                    return $sampler;
                })->implode(', ');

                $driverJadwal = trim((string) ($first->driver ?? ''));
                $kendaraanJadwal = trim((string) ($first->kendaraan ?? ''));

                return [
                    'id_jadwal' => $ids->all(),
                    'team_sampler' => $normalizedTeam,
                    'display_sampler' => $displaySampler,
                    'no_quotation' => $first->no_quotation,
                    'nama_perusahaan' => $first->nama_perusahaan,
                    'wilayah' => $first->wilayah,
                    'jam_mulai' => $first->jam_mulai,
                    'jam_selesai' => $first->jam_selesai,
                    'durasi' => $first->durasi,
                    'periode' => $first->periode,
                    'pic' => $first->pic,
                    'note' => $first->note,
                    'kategori' => $kategoriItems,
                    'ringkasan_kategori' => $this->summarizeKategori($kategoriItems),
                    'driver' => $driverJadwal !== '' ? $driverJadwal : null,
                    'kendaraan' => $kendaraanJadwal !== '' ? $kendaraanJadwal : null,
                ];
            })
            ->groupBy('team_sampler')
            ->map(function ($group) {
                $first = $group->first();
                $allIds = $group->pluck('id_jadwal')->flatten()->unique()->values()->all();
                $maxDurasi = (int) $group->max('durasi');
                $driverTim = $group->pluck('driver')->map(function ($name) {
                    return trim((string) $name);
                })->filter()->unique()->values()->first();
                $kendaraanTim = $group->pluck('kendaraan')->map(function ($plat) {
                    return trim((string) $plat);
                })->filter()->unique()->values()->first();

                return [
                    'tim_key' => md5(implode(',', $allIds)),
                    'tim_sampler' => $first['display_sampler'],
                    'id_jadwal' => $allIds,
                    'durasi' => $maxDurasi,
                    'driver' => $driverTim ?: null,
                    'kendaraan' => $kendaraanTim ?: null,
                    'list_pt' => $group->map(function ($item) {
                        return [
                            'id_jadwal' => $item['id_jadwal'],
                            'no_quotation' => $item['no_quotation'],
                            'nama_perusahaan' => $item['nama_perusahaan'],
                            'wilayah' => $item['wilayah'],
                            'sampler' => $item['display_sampler'],
                            'jam_mulai' => $item['jam_mulai'],
                            'jam_selesai' => $item['jam_selesai'],
                            'durasi' => $item['durasi'],
                            'periode' => $item['periode'],
                            'pic' => $item['pic'],
                            'note' => $item['note'],
                            'kategori' => $item['kategori'],
                            'ringkasan_kategori' => $item['ringkasan_kategori'],
                        ];
                    })->values(),
                ];
            })
            ->values();
    }

    protected function parseKategori($raw)
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded)));
        }

        return [trim($raw)];
    }

    protected function summarizeKategori(array $items)
    {
        $counts = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            $parts = explode(' - ', $item, 2);
            $nama = trim(preg_replace('/^\d+-/', '', $parts[0]));
            if ($nama === '') {
                continue;
            }

            if (!isset($counts[$nama])) {
                $counts[$nama] = 0;
            }
            $counts[$nama]++;
        }

        $summary = [];
        foreach ($counts as $nama => $titik) {
            $summary[] = [
                'nama' => $nama,
                'titik' => $titik,
                'label' => $nama . ' ' . $titik . ' titik',
            ];
        }

        return $summary;
    }

    protected function isSamePerson($a, $b)
    {
        $a = trim((string) $a);
        $b = trim((string) $b);
        if ($a === '' || $b === '') {
            return false;
        }

        return mb_strtolower($a) === mb_strtolower($b);
    }

    protected function presentMo($mo)
    {
        $jadwalRows = $mo->details->map(function ($detail) use ($mo) {
            $resolved = $this->resolveJadwalForDetail($detail, $mo->tanggal_sampling);
            if ($resolved) {
                return $resolved;
            }

            return (object) [
                'id' => $detail->id_jadwal,
                'sampler' => $detail->sampler,
                'no_quotation' => $detail->no_quotation,
                'nama_perusahaan' => $detail->nama_perusahaan,
                'wilayah' => $detail->wilayah,
                'jam_mulai' => $detail->jam_mulai,
                'jam_selesai' => $detail->jam_selesai,
                'durasi' => $detail->durasi,
                'periode' => null,
                'note' => null,
                'kategori' => null,
                'driver' => $mo->nama_driver,
                'kendaraan' => $mo->plat_mobil,
                'parsial' => null,
                'id_cabang' => null,
                'quotationKontrakH' => null,
                'quotationNonKontrak' => null,
            ];
        });

        $tim = $this->groupJadwalAsTim($jadwalRows, $mo->nama_driver);

        $listPt = collect($tim)->pluck('list_pt')->flatten(1);
        $namaPerusahaan = $listPt->pluck('nama_perusahaan')->filter()->unique()->values()->all();
        $allKategori = $listPt->pluck('kategori')->flatten()->filter()->unique()->values()->all();

        return [
            'id' => $mo->id,
            'id_mobil' => $mo->id_mobil,
            'plat_mobil' => $mo->plat_mobil,
            'id_driver' => $mo->id_driver,
            'nama_driver' => $mo->nama_driver,
            'tanggal_sampling' => $mo->tanggal_sampling,
            'tanggal_pulang' => $mo->tanggal_pulang,
            'jam_keberangkatan' => $mo->jam_keberangkatan
                ? substr($mo->jam_keberangkatan, 0, 5)
                : null,
            'durasi' => $mo->durasi,
            'catatan' => $mo->catatan,
            'id_jadwal' => $mo->details->pluck('id_jadwal')->map(function ($id) {
                return (int) $id;
            })->values()->all(),
            'tim' => $tim,
            'nama_perusahaan' => $namaPerusahaan,
            'ringkasan_kategori' => $this->summarizeKategori($allKategori),
            'created_by' => $mo->created_by,
            'created_at' => $mo->created_at,
        ];
    }

    protected function assertJadwalAvailable(array $idJadwal, $excludeMoId = null)
    {
        if (!$this->tablesReady()) {
            return;
        }

        $rows = Jadwal::whereIn('id', $idJadwal)->where('is_active', true)->get();
        foreach ($rows as $row) {
            $query = MobilisasiOperasionalDetail::where('is_active', true);
            if ($excludeMoId) {
                $query->where('id_mo', '!=', $excludeMoId);
            }

            $query->where(function ($match) use ($row) {
                $match->where('id_jadwal', $row->id);
                if ($this->detailHasSamplingColumns() && $row->id_sampling) {
                    $match->orWhere(function ($samplingMatch) use ($row) {
                        $samplingMatch->where('id_sampling', $row->id_sampling);
                        if ($row->parsial) {
                            $samplingMatch->where('parsial', $row->parsial);
                        } else {
                            $samplingMatch->whereNull('parsial');
                        }
                        if ($row->userid) {
                            $samplingMatch->where('userid', $row->userid);
                        } else {
                            $samplingMatch->where('sampler', $row->sampler);
                        }
                    });
                }
            });

            if ($query->exists()) {
                throw new Exception('Ada tim yang sudah diatur di mobilisasi operasional lain.', 422);
            }
        }
    }

    protected function normalizeIds($ids)
    {
        return collect(is_array($ids) ? $ids : explode(',', (string) $ids))
            ->map(function ($id) {
                return (int) $id;
            })
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values()
            ->all();
    }

    protected function resolveKendaraan(array $payload)
    {
        $idMobil = $payload['id_mobil'] ?? null;
        $plat = trim((string) ($payload['plat_mobil'] ?? ''));

        $query = DaftarMobil::query()->where('is_active', true);

        $kendaraan = null;
        if (is_numeric($idMobil)) {
            $kendaraan = (clone $query)->where('id', (int) $idMobil)->first();
        }

        if (!$kendaraan && $plat !== '') {
            $kendaraan = (clone $query)->where('plat_mobil', $plat)->first();
        }

        if (!$kendaraan) {
            throw new Exception('Kendaraan tidak ditemukan atau tidak aktif.', 422);
        }

        return $kendaraan;
    }

    protected function fetchKendaraanAvailable($tanggal, $excludeMoId = null, $durasi = null)
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $durasi = $durasi === null ? 0 : (int) $durasi;
        $windowEnd = $this->hitungTanggalPulang($tanggal, $durasi);

        $allMobil = DaftarMobil::where('is_active', true)
            ->whereNotNull('plat_mobil')
            ->where('plat_mobil', '!=', '')
            ->orderBy('plat_mobil')
            ->get();

        $conflicts = [];
        $available = collect();

        foreach ($allMobil as $item) {
            $plat = trim((string) $item->plat_mobil);
            $overlap = $this->findOverlappingMobilMo(
                $plat,
                (int) $item->id,
                $tanggal,
                $windowEnd,
                $excludeMoId
            );

            if ($overlap) {
                $conflicts[$plat] = $this->presentMobilConflict($overlap);
                continue;
            }

            $available->push($this->presentKendaraanOption($item));
        }

        return [
            'data' => $available->values(),
            'conflicts' => $conflicts,
        ];
    }

    protected function presentMobilConflict($mo)
    {
        $pulang = $mo->tanggal_pulang ?: $mo->tanggal_sampling;
        $availableAgain = Carbon::parse($pulang)->addDay()->format('Y-m-d');

        return [
            'id_mo' => $mo->id,
            'plat_mobil' => $mo->plat_mobil,
            'tanggal_berangkat' => $mo->tanggal_sampling,
            'tanggal_pulang' => $pulang,
            'jam_keberangkatan' => $mo->jam_keberangkatan
                ? substr($mo->jam_keberangkatan, 0, 5)
                : null,
            'nama_driver' => $mo->nama_driver,
            'tersedia_lagi_dari' => $availableAgain,
        ];
    }

    protected function mobilUsageRangesOverlap($startA, $endA, $startB, $endB)
    {
        $startA = Carbon::parse($startA)->toDateString();
        $endA = Carbon::parse($endA ?: $startA)->toDateString();
        $startB = Carbon::parse($startB)->toDateString();
        $endB = Carbon::parse($endB ?: $startB)->toDateString();

        return !($endA < $startB || $startA > $endB);
    }

    protected function findOverlappingMobilMo($plat, $idMobil, $tanggalBerangkat, $tanggalPulang, $excludeMoId = null)
    {
        if (!$this->tablesReady()) {
            return null;
        }

        $plat = trim((string) $plat);
        if ($plat === '') {
            return null;
        }

        $query = MobilisasiOperasional::where('is_active', true)
            ->where(function ($match) use ($plat, $idMobil) {
                $match->where('plat_mobil', $plat);
                if ($idMobil > 0) {
                    $match->orWhere('id_mobil', $idMobil);
                }
            });

        if ($excludeMoId) {
            $query->where('id', '!=', (int) $excludeMoId);
        }

        foreach ($query->get() as $mo) {
            $moStart = $mo->tanggal_sampling;
            $moEnd = $mo->tanggal_pulang ?: $mo->tanggal_sampling;
            if ($this->mobilUsageRangesOverlap($tanggalBerangkat, $tanggalPulang, $moStart, $moEnd)) {
                return $mo;
            }
        }

        return null;
    }

    protected function assertMobilAvailable($idMobil, $plat, $tanggalBerangkat, $tanggalPulang, $excludeMoId = null)
    {
        $overlap = $this->findOverlappingMobilMo(
            $plat,
            (int) $idMobil,
            $tanggalBerangkat,
            $tanggalPulang,
            $excludeMoId
        );

        if (!$overlap) {
            return;
        }

        $pulang = $overlap->tanggal_pulang ?: $overlap->tanggal_sampling;
        $availableAgain = Carbon::parse($pulang)->addDay()->format('d/m/Y');

        throw new Exception(
            'Mobil ' . $plat . ' tidak tersedia: sedang dipakai mulai '
            . Carbon::parse($overlap->tanggal_sampling)->format('d/m/Y')
            . ' hingga ' . Carbon::parse($pulang)->format('d/m/Y')
            . '. Bisa dipilih lagi mulai ' . $availableAgain . '.',
            422
        );
    }

    protected function presentKendaraanOption($item)
    {
        $plat = trim((string) $item->plat_mobil);
        $merk = trim((string) $item->merk_mobil);

        return [
            'id' => $item->id,
            'text' => $merk !== '' ? $plat . ' — ' . $merk : $plat,
            'plat_mobil' => $plat,
            'merk_mobil' => $merk !== '' ? $merk : null,
        ];
    }

    protected function tablesReady()
    {
        if (self::$moTablesReadyCache === null) {
            self::$moTablesReadyCache = Schema::hasTable('mobilisasi_operasional')
                && Schema::hasTable('mobilisasi_operasional_detail');
        }

        return self::$moTablesReadyCache;
    }

    protected function historyColumns()
    {
        $columns = [];
        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            return $columns;
        }

        foreach (['alasan_perubahan', 'sumber_perubahan', 'id_jadwal_asal', 'sampler_sebelum'] as $column) {
            if (Schema::hasColumn('mobilisasi_operasional_detail', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    protected function historyPayload(array $fields)
    {
        $allowed = $this->historyColumns();
        $payload = [];
        foreach ($fields as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    protected function makeDetailHistoryPayload(
        $idMo,
        $jadwal,
        $actor,
        $now,
        $alasan,
        $sumber,
        $idJadwalAsal = null,
        $samplerSebelum = null
    ) {
        $payload = [
            'id_mo' => $idMo,
            'id_jadwal' => $jadwal->id,
            'sampler' => $jadwal->sampler,
            'no_quotation' => $jadwal->no_quotation,
            'nama_perusahaan' => $jadwal->nama_perusahaan,
            'jam_mulai' => $jadwal->jam_mulai,
            'jam_selesai' => $jadwal->jam_selesai,
            'wilayah' => $jadwal->wilayah,
            'durasi' => $jadwal->durasi,
            'created_at' => $now,
            'created_by' => $actor,
            'is_active' => true,
        ];

        if ($this->detailHasSamplingColumns()) {
            $payload['id_sampling'] = $jadwal->id_sampling;
            $payload['userid'] = $jadwal->userid;
            $payload['parsial'] = $jadwal->parsial;
        }

        return array_merge($payload, $this->historyPayload([
            'alasan_perubahan' => $alasan,
            'sumber_perubahan' => $sumber,
            'id_jadwal_asal' => $idJadwalAsal,
            'sampler_sebelum' => $samplerSebelum,
        ]));
    }

    protected function detailHasSamplingColumns()
    {
        if (self::$detailSamplingColumnsCache === null) {
            self::$detailSamplingColumnsCache = Schema::hasTable('mobilisasi_operasional_detail')
                && Schema::hasColumn('mobilisasi_operasional_detail', 'id_sampling');
        }

        return self::$detailSamplingColumnsCache;
    }

    protected function resolveJadwalForDetail($detail, $tanggal = null)
    {
        if ($detail->relationLoaded('jadwal') && $detail->jadwal && $detail->jadwal->is_active) {
            return $detail->jadwal;
        }

        if ($detail->id_jadwal) {
            $byId = Jadwal::where('id', $detail->id_jadwal)->where('is_active', true)->first();
            if ($byId) {
                return $byId;
            }
        }

        if (!$this->detailHasSamplingColumns() || !$detail->id_sampling) {
            return null;
        }

        $query = Jadwal::where('is_active', true)
            ->where('id_sampling', $detail->id_sampling);

        if ($tanggal) {
            $query->where('tanggal', $tanggal);
        }

        if ($detail->parsial) {
            $query->where('parsial', $detail->parsial);
        } else {
            $query->whereNull('parsial');
        }

        if ($detail->userid) {
            $query->where('userid', $detail->userid);
        } elseif ($detail->sampler) {
            $query->where('sampler', $detail->sampler);
        }

        return $query->orderBy('id', 'desc')->first();
    }

    protected function collectVisitScopesFromDetails($details)
    {
        return collect($details)->map(function ($detail) {
            $idSampling = $detail->id_sampling;
            if (!$idSampling && $detail->id_jadwal) {
                $idSampling = Jadwal::where('id', $detail->id_jadwal)->value('id_sampling');
            }

            return [
                'id_sampling' => $idSampling,
                'parsial' => $detail->parsial,
            ];
        })->filter(function ($scope) {
            return !empty($scope['id_sampling']);
        })->unique(function ($scope) {
            return $scope['id_sampling'] . '|' . ($scope['parsial'] ?: 'null');
        })->values();
    }

    protected function loadActiveJadwalForVisitScopes($visitScopes, $tanggal)
    {
        $rows = collect();
        foreach ($visitScopes as $scope) {
            $query = Jadwal::where('is_active', true)
                ->where('tanggal', $tanggal)
                ->where('id_sampling', $scope['id_sampling']);

            if (!empty($scope['parsial'])) {
                $query->where('parsial', $scope['parsial']);
            } else {
                $query->whereNull('parsial');
            }

            $rows = $rows->merge($query->get());
        }

        return $rows->unique('id')->values();
    }

    protected function findActiveDetailForJadwal($idMo, $jadwal)
    {
        $query = MobilisasiOperasionalDetail::where('id_mo', $idMo)->where('is_active', true);

        if ($this->detailHasSamplingColumns() && $jadwal->id_sampling) {
            $scoped = clone $query;
            $scoped->where('id_sampling', $jadwal->id_sampling);
            if ($jadwal->parsial) {
                $scoped->where('parsial', $jadwal->parsial);
            } else {
                $scoped->whereNull('parsial');
            }
            if ($jadwal->userid) {
                $scoped->where('userid', $jadwal->userid);
            } else {
                $scoped->where('sampler', $jadwal->sampler);
            }
            $found = $scoped->first();
            if ($found) {
                return $found;
            }
        }

        return $query->where('id_jadwal', $jadwal->id)->first();
    }

    protected function applyDetailSnapshot($detail, $jadwal, $actor, $now)
    {
        $detail->id_jadwal = $jadwal->id;
        $detail->sampler = $jadwal->sampler;
        $detail->no_quotation = $jadwal->no_quotation;
        $detail->nama_perusahaan = $jadwal->nama_perusahaan;
        $detail->jam_mulai = $jadwal->jam_mulai;
        $detail->jam_selesai = $jadwal->jam_selesai;
        $detail->wilayah = $jadwal->wilayah;
        $detail->durasi = $jadwal->durasi;
        $detail->is_active = true;
        $detail->updated_at = $now;
        $detail->updated_by = $actor;

        if ($this->detailHasSamplingColumns()) {
            $detail->id_sampling = $jadwal->id_sampling;
            $detail->userid = $jadwal->userid;
            $detail->parsial = $jadwal->parsial;
        }

        $detail->save();
    }

    protected function syncMoHeaderFromJadwalRows(
        $header,
        $jadwalRows,
        $actor,
        $now,
        $alasan,
        $sumber,
        $deactivateMissing = true
    ) {
        $jadwalRows = collect($jadwalRows)->filter(function ($row) {
            return $row && $row->is_active;
        })->unique('id')->values();

        $keptDetailIds = [];

        foreach ($jadwalRows as $row) {
            $detail = $this->findActiveDetailForJadwal($header->id, $row);
            if (!$detail) {
                $detail = new MobilisasiOperasionalDetail(array_merge(
                    $this->makeDetailHistoryPayload(
                        $header->id,
                        $row,
                        $actor,
                        $now,
                        $alasan,
                        $sumber
                    ),
                    ['updated_at' => $now, 'updated_by' => $actor]
                ));
            }

            $this->applyDetailSnapshot($detail, $row, $actor, $now);
            $keptDetailIds[] = $detail->id;
        }

        if ($deactivateMissing) {
            $staleQuery = MobilisasiOperasionalDetail::where('id_mo', $header->id)
                ->where('is_active', true);

            if (count($keptDetailIds) > 0) {
                $staleQuery->whereNotIn('id', $keptDetailIds);
            }

            $stale = $staleQuery->get();
            if ($stale->isNotEmpty()) {
                $this->deactivateDetails($stale, $actor, $now, $alasan, $sumber);
            }
        }

        $linkedIds = MobilisasiOperasionalDetail::where('id_mo', $header->id)
            ->where('is_active', true)
            ->pluck('id_jadwal')
            ->all();

        if (count($linkedIds) === 0) {
            $header->is_active = false;
            $header->deleted_at = $now;
            $header->deleted_by = $actor;
            $header->save();
            return;
        }

        $activeJadwal = Jadwal::whereIn('id', $linkedIds)->where('is_active', true)->get();
        $this->syncHeaderFromJadwal($header, $activeJadwal, $actor, $now);
        $this->applyLegacyColumns($linkedIds, $header->plat_mobil, $header->nama_driver);
    }

    protected function deactivateDetails($details, $actor, $now, $alasan, $sumber)
    {
        $ids = collect($details)->pluck('id')->filter()->values()->all();
        if (count($ids) === 0) {
            return;
        }

        MobilisasiOperasionalDetail::whereIn('id', $ids)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'deleted_at' => $now,
                'deleted_by' => $actor,
            ]);
    }

    protected function normalizeSamplerName($name)
    {
        $name = preg_replace('/\s*\(driver\)\s*/i', '', (string) $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return mb_strtolower($name);
    }

    protected function syncHeaderFromJadwal($header, $newJadwal, $actor, $now)
    {
        $formDrivers = $newJadwal->pluck('driver')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter()
            ->unique()
            ->values();
        $formPlats = $newJadwal->pluck('kendaraan')
            ->map(function ($value) {
                return trim((string) $value);
            })
            ->filter()
            ->unique()
            ->values();

        $dirty = false;

        if ($formDrivers->count() === 1 && $formDrivers->first() !== trim((string) $header->nama_driver)) {
            $newDriver = $formDrivers->first();
            $master = MasterDriver::where('is_active', true)
                ->where('nama_driver', $newDriver)
                ->first();
            $header->nama_driver = $newDriver;
            if ($master) {
                $header->id_driver = $master->user_id;
            }
            $dirty = true;
        }

        if ($formPlats->count() === 1 && $formPlats->first() !== trim((string) $header->plat_mobil)) {
            $newPlat = $formPlats->first();
            $kendaraan = DaftarMobil::where('is_active', true)
                ->where('plat_mobil', $newPlat)
                ->first();
            $header->plat_mobil = $newPlat;
            if ($kendaraan) {
                $header->id_mobil = $kendaraan->id;
            }
            $dirty = true;
        }

        if ($dirty) {
            $header->updated_at = $now;
            $header->updated_by = $actor;
            $header->save();
        }

        $linkedIds = MobilisasiOperasionalDetail::where('id_mo', $header->id)
            ->where('is_active', true)
            ->pluck('id_jadwal')
            ->all();

        $this->applyLegacyColumns($linkedIds, $header->plat_mobil, $header->nama_driver);
    }
}
