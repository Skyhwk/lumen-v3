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
 * Sumber baru: tabel mobilisasi_operasional (+ detail by id_jadwal).
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
        $rows = MobilisasiOperasional::with(['details.jadwal'])
            ->where('is_active', true)
            ->where('tanggal_sampling', $tanggal)
            ->orderBy('jam_keberangkatan')
            ->orderBy('id')
            ->get();

        return $rows->map(function ($mo) {
            return $this->presentMo($mo);
        })->values();
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

    public function getOptions($tanggal, $excludeMoId = null)
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

        $kendaraan = $this->fetchKendaraanAvailable($tanggal, $excludeMoId);

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
                            if ($detail->jadwal) {
                                return $detail->jadwal;
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

                $oldIds = $header->details()->pluck('id_jadwal')->all();
                $this->clearLegacyColumns($oldIds);

                MobilisasiOperasionalDetail::where('id_mo', $header->id)
                    ->where('is_active', true)
                    ->update(array_merge([
                        'is_active' => false,
                        'deleted_at' => $now,
                        'deleted_by' => $actor,
                    ], $this->historyPayload([
                        'alasan_perubahan' => 'Edit atur mobilisasi operasional',
                        'sumber_perubahan' => 'atur_mo',
                    ])));

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

            foreach ($jadwalRows as $row) {
                MobilisasiOperasionalDetail::create(array_merge([
                    'id_mo' => $header->id,
                    'id_jadwal' => $row->id,
                    'sampler' => $row->sampler,
                    'no_quotation' => $row->no_quotation,
                    'nama_perusahaan' => $row->nama_perusahaan,
                    'jam_mulai' => $row->jam_mulai,
                    'jam_selesai' => $row->jam_selesai,
                    'wilayah' => $row->wilayah,
                    'durasi' => $row->durasi,
                    'created_at' => $now,
                    'created_by' => $actor,
                    'is_active' => true,
                ], $this->historyPayload([
                    'alasan_perubahan' => $moId
                        ? 'Edit atur mobilisasi operasional'
                        : 'Atur mobilisasi operasional',
                    'sumber_perubahan' => 'atur_mo',
                ])));
            }

            $this->applyLegacyColumns(
                $idJadwal,
                $plat,
                $driver->nama_driver
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
     * Dipanggil dari JadwalServices saat baris jadwal diganti (id lama mati, id baru insert)
     * atau sampler/driver di-update in-place.
     *
     * Jangan menimpa baris detail: nonaktifkan yang lama, insert baris baru
     * supaya pergerakan tim/driver bisa diaudit (pelaku, waktu, alasan).
     */
    public function remapAfterJadwalReplace(array $oldIds, array $newIds, $actor = null, $alasan = null)
    {
        if (!$this->tablesReady()) {
            return;
        }

        $oldIds = $this->normalizeIds($oldIds);
        $newIds = $this->normalizeIds($newIds);
        if (count($oldIds) === 0) {
            return;
        }

        $details = MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_jadwal', $oldIds)
            ->get();

        if ($details->isEmpty()) {
            return;
        }

        $actor = $actor ?: 'System';
        $alasan = $alasan ?: 'Update jadwal sampling plan';
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $newJadwal = Jadwal::whereIn('id', count($newIds) ? $newIds : $oldIds)
            ->where('is_active', true)
            ->get();

        if ($newJadwal->isEmpty()) {
            $this->deactivateDetails($details, $actor, $now, $alasan, 'update_jadwal');
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

            $pairs = $this->pairDetailsToNewJadwal($moDetails, $newJadwal);
            $pairedOldIds = [];
            $formDrivers = $newJadwal->pluck('driver')
                ->map(function ($value) {
                    return trim((string) $value);
                })
                ->filter()
                ->unique()
                ->values();
            $driverWillChange = $formDrivers->count() === 1
                && $formDrivers->first() !== trim((string) $header->nama_driver);
            $reason = $alasan;
            if ($driverWillChange) {
                $reason .= ' | driver: ' . trim((string) $header->nama_driver) . ' → ' . $formDrivers->first();
            }

            foreach ($pairs as $pair) {
                $old = $pair['old'];
                $row = $pair['new'];
                if ($old) {
                    $pairedOldIds[] = $old->id;
                    $this->deactivateDetails(collect([$old]), $actor, $now, $reason, 'update_jadwal');
                }

                MobilisasiOperasionalDetail::create($this->makeDetailHistoryPayload(
                    $header->id,
                    $row,
                    $actor,
                    $now,
                    $this->buildReplacementReason($reason, $old, $row),
                    'update_jadwal',
                    $old ? $old->id_jadwal : null,
                    $old ? $old->sampler : null
                ));
            }

            $unpaired = $moDetails->filter(function ($detail) use ($pairedOldIds) {
                return !in_array($detail->id, $pairedOldIds, true);
            });
            if ($unpaired->isNotEmpty()) {
                $this->deactivateDetails($unpaired, $actor, $now, $reason, 'update_jadwal');
            }

            $this->syncHeaderFromJadwal($header, $newJadwal, $actor, $now);

            Log::channel('sampling')->info('MO remap setelah update jadwal', [
                'id_mo' => $header->id,
                'actor' => $actor,
                'alasan' => $reason,
                'old_ids' => $oldIds,
                'new_ids' => $newJadwal->pluck('id')->all(),
            ]);
        }
    }

    public function syncSnapshots(array $jadwalIds, $actor = null, $alasan = null)
    {
        if (!$this->tablesReady()) {
            return;
        }

        $jadwalIds = $this->normalizeIds($jadwalIds);
        if (count($jadwalIds) === 0) {
            return;
        }

        $rows = Jadwal::whereIn('id', $jadwalIds)->get()->keyBy('id');
        $details = MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_jadwal', $jadwalIds)
            ->get();

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $actor = $actor ?: 'System';
        $alasan = $alasan ?: 'Update jadwal sampling plan';

        foreach ($details as $detail) {
            $jadwal = $rows->get($detail->id_jadwal);
            if (!$jadwal) {
                continue;
            }

            if ($this->normalizeSamplerName($detail->sampler) !== $this->normalizeSamplerName($jadwal->sampler)) {
                $this->deactivateDetails(collect([$detail]), $actor, $now, $alasan, 'update_jadwal');
                MobilisasiOperasionalDetail::create($this->makeDetailHistoryPayload(
                    $detail->id_mo,
                    $jadwal,
                    $actor,
                    $now,
                    $this->buildReplacementReason($alasan, $detail, $jadwal),
                    'update_jadwal',
                    $detail->id_jadwal,
                    $detail->sampler
                ));
                continue;
            }

            $detail->no_quotation = $jadwal->no_quotation;
            $detail->nama_perusahaan = $jadwal->nama_perusahaan;
            $detail->jam_mulai = $jadwal->jam_mulai;
            $detail->jam_selesai = $jadwal->jam_selesai;
            $detail->wilayah = $jadwal->wilayah;
            $detail->durasi = $jadwal->durasi;
            $detail->updated_at = $now;
            $detail->updated_by = $actor;
            $detail->save();
        }
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
                ];
            })
            ->groupBy('team_sampler')
            ->map(function ($group) {
                $first = $group->first();
                $allIds = $group->pluck('id_jadwal')->flatten()->unique()->values()->all();
                $maxDurasi = (int) $group->max('durasi');

                return [
                    'tim_key' => md5(implode(',', $allIds)),
                    'tim_sampler' => $first['display_sampler'],
                    'id_jadwal' => $allIds,
                    'durasi' => $maxDurasi,
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
        $jadwalRows = $mo->details->map(function ($detail) {
            return $detail->jadwal ?: (object) [
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

        $query = MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_jadwal', $idJadwal);

        if ($excludeMoId) {
            $query->where('id_mo', '!=', $excludeMoId);
        }

        if ($query->exists()) {
            throw new Exception('Ada tim yang sudah diatur di mobilisasi operasional lain.', 422);
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

    protected function fetchKendaraanAvailable($tanggal, $excludeMoId = null)
    {
        $data = DaftarMobil::where('is_active', true)
            ->whereNotNull('plat_mobil')
            ->where('plat_mobil', '!=', '')
            ->orderBy('plat_mobil')
            ->get()
            ->map(function ($item) {
                return $this->presentKendaraanOption($item);
            })
            ->values();

        $queryBentrok = DB::table('jadwal')
            ->select('kendaraan', 'sampler', 'jam_mulai', 'created_at', 'updated_at')
            ->where('tanggal', $tanggal)
            ->whereNotNull('kendaraan')
            ->where('is_active', 1);

        $detailKonflik = [];
        foreach ($queryBentrok->get() as $row) {
            $veh = $row->kendaraan;
            if (!isset($detailKonflik[$veh])) {
                $detailKonflik[$veh] = $row;
                continue;
            }

            $existing = $detailKonflik[$veh];
            if ($row->jam_mulai < $existing->jam_mulai) {
                $detailKonflik[$veh] = $row;
            } elseif ($row->jam_mulai == $existing->jam_mulai) {
                $waktuRow = $row->created_at ? strtotime($row->created_at) : strtotime($row->updated_at);
                $waktuExisting = $existing->created_at ? strtotime($existing->created_at) : strtotime($existing->updated_at);
                if ($waktuRow && $waktuExisting && $waktuRow < $waktuExisting) {
                    $detailKonflik[$veh] = $row;
                }
            }
        }

        return [
            'data' => $data,
            'conflicts' => $detailKonflik,
        ];
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
        return Schema::hasTable('mobilisasi_operasional')
            && Schema::hasTable('mobilisasi_operasional_detail');
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
        return array_merge([
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
        ], $this->historyPayload([
            'alasan_perubahan' => $alasan,
            'sumber_perubahan' => $sumber,
            'id_jadwal_asal' => $idJadwalAsal,
            'sampler_sebelum' => $samplerSebelum,
        ]));
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

    protected function pairDetailsToNewJadwal($moDetails, $newJadwal)
    {
        $oldJadwal = Jadwal::whereIn('id', $moDetails->pluck('id_jadwal')->all())
            ->get()
            ->keyBy('id');
        $used = [];
        $pairs = [];
        $pairedOld = [];

        $take = function ($predicate) use ($newJadwal, &$used) {
            foreach ($newJadwal as $row) {
                if (isset($used[$row->id])) {
                    continue;
                }
                if ($predicate($row)) {
                    $used[$row->id] = true;
                    return $row;
                }
            }

            return null;
        };

        foreach ($moDetails as $detail) {
            $match = $take(function ($row) use ($detail) {
                return (int) $row->id === (int) $detail->id_jadwal;
            });
            if ($match) {
                $pairs[] = ['old' => $detail, 'new' => $match];
                $pairedOld[$detail->id] = true;
            }
        }

        foreach ($moDetails as $detail) {
            if (isset($pairedOld[$detail->id])) {
                continue;
            }
            $old = $oldJadwal->get($detail->id_jadwal);
            if (!$old || $old->userid === null || $old->userid === '') {
                continue;
            }
            $match = $take(function ($row) use ($old) {
                return (string) $row->userid === (string) $old->userid
                    && (string) $row->no_quotation === (string) $old->no_quotation;
            });
            if ($match) {
                $pairs[] = ['old' => $detail, 'new' => $match];
                $pairedOld[$detail->id] = true;
            }
        }

        foreach ($moDetails as $detail) {
            if (isset($pairedOld[$detail->id])) {
                continue;
            }
            $match = $take(function ($row) use ($detail) {
                return $this->normalizeSamplerName($row->sampler) === $this->normalizeSamplerName($detail->sampler);
            });
            if ($match) {
                $pairs[] = ['old' => $detail, 'new' => $match];
                $pairedOld[$detail->id] = true;
            }
        }

        $unpairedOld = $moDetails->filter(function ($detail) use ($pairedOld) {
            return !isset($pairedOld[$detail->id]);
        })->values();
        $leftoverNew = $newJadwal->filter(function ($row) use ($used) {
            return !isset($used[$row->id]);
        })->values();

        $limit = min($unpairedOld->count(), $leftoverNew->count());
        for ($i = 0; $i < $limit; $i++) {
            $pairs[] = ['old' => $unpairedOld[$i], 'new' => $leftoverNew[$i]];
            $used[$leftoverNew[$i]->id] = true;
        }

        for ($i = $limit; $i < $leftoverNew->count(); $i++) {
            $pairs[] = ['old' => null, 'new' => $leftoverNew[$i]];
        }

        return $pairs;
    }

    protected function normalizeSamplerName($name)
    {
        $name = preg_replace('/\s*\(driver\)\s*/i', '', (string) $name);
        $name = preg_replace('/\s+/', ' ', trim($name));

        return mb_strtolower($name);
    }

    protected function buildReplacementReason($baseAlasan, $oldDetail, $newJadwal)
    {
        $parts = [trim((string) $baseAlasan)];
        if ($oldDetail && $newJadwal) {
            $from = trim((string) $oldDetail->sampler);
            $to = trim((string) $newJadwal->sampler);
            if ($this->normalizeSamplerName($from) !== $this->normalizeSamplerName($to)) {
                $parts[] = $from . ' diganti ' . $to;
            }
            if ((int) $oldDetail->id_jadwal !== (int) $newJadwal->id) {
                $parts[] = 'id_jadwal ' . $oldDetail->id_jadwal . ' → ' . $newJadwal->id;
            }
        } elseif ($newJadwal) {
            $parts[] = 'Anggota baru: ' . trim((string) $newJadwal->sampler);
        }

        return implode(' | ', array_filter($parts));
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
