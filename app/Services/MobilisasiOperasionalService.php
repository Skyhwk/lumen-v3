<?php

namespace App\Services;

use App\Models\DaftarMobil;
use App\Models\Jadwal;
use App\Models\MasterDriver;
use App\Models\MobilisasiOperasional;
use App\Models\MobilisasiOperasionalDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
 * Tab Belum = jadwal tanggal itu yang driver DAN kendaraan masih kosong,
 * dan belum masuk tabel MO.
 */
class MobilisasiOperasionalService
{
    public const DUAL_WRITE_KENDARAAN = true;
    public const DUAL_WRITE_DRIVER = true;

    /** jadwal_columns = driver & kendaraan masih kosong. mo_table = hanya cek tabel MO. */
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

        $plat = trim((string) ($payload['plat_mobil'] ?? $payload['id_mobil'] ?? ''));
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

        $daftarMobil = DaftarMobil::where('is_active', true)
            ->where('plat_mobil', $plat)
            ->first();

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
                    ->update([
                        'is_active' => false,
                        'deleted_at' => $now,
                        'deleted_by' => $actor,
                    ]);

                $header->id_mobil = $daftarMobil ? $daftarMobil->id : null;
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
                    'id_mobil' => $daftarMobil ? $daftarMobil->id : null,
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
                MobilisasiOperasionalDetail::create([
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
                ]);
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
     * Dipanggil dari JadwalServices saat baris jadwal diganti (id lama mati, id baru insert).
     * Link MO pindah ke id baru + dual-write kendaraan/driver ke baris baru.
     */
    public function remapAfterJadwalReplace(array $oldIds, array $newIds)
    {
        if (!$this->tablesReady()) {
            return;
        }

        $oldIds = $this->normalizeIds($oldIds);
        $newIds = $this->normalizeIds($newIds);
        if (count($oldIds) === 0 || count($newIds) === 0) {
            return;
        }

        $details = MobilisasiOperasionalDetail::where('is_active', true)
            ->whereIn('id_jadwal', $oldIds)
            ->get();

        if ($details->isEmpty()) {
            $this->syncSnapshots($newIds);
            return;
        }

        $oldJadwal = Jadwal::whereIn('id', $oldIds)->get()->keyBy('id');
        $newJadwal = Jadwal::whereIn('id', $newIds)->get();
        $newPool = $newJadwal->groupBy(function ($row) {
            return $row->no_quotation . '|' . $row->userid;
        });

        $usedNew = [];
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $affectedMo = [];

        foreach ($details as $detail) {
            $old = $oldJadwal->get($detail->id_jadwal);
            $matched = null;

            if ($old) {
                $key = $old->no_quotation . '|' . $old->userid;
                $candidates = $newPool->get($key, collect())->values();
                foreach ($candidates as $candidate) {
                    if (!isset($usedNew[$candidate->id])) {
                        $matched = $candidate;
                        break;
                    }
                }
            }

            if (!$matched && $old) {
                $fallback = $newJadwal->first(function ($row) use ($old, $usedNew) {
                    return $row->no_quotation === $old->no_quotation
                        && !isset($usedNew[$row->id]);
                });
                $matched = $fallback;
            }

            if (!$matched) {
                continue;
            }

            $usedNew[$matched->id] = true;
            $detail->id_jadwal = $matched->id;
            $detail->sampler = $matched->sampler;
            $detail->no_quotation = $matched->no_quotation;
            $detail->nama_perusahaan = $matched->nama_perusahaan;
            $detail->jam_mulai = $matched->jam_mulai;
            $detail->jam_selesai = $matched->jam_selesai;
            $detail->wilayah = $matched->wilayah;
            $detail->durasi = $matched->durasi;
            $detail->updated_at = $now;
            $detail->save();

            $affectedMo[$detail->id_mo] = true;
        }

        foreach (array_keys($affectedMo) as $moId) {
            $header = MobilisasiOperasional::where('id', $moId)
                ->where('is_active', true)
                ->first();
            if (!$header) {
                continue;
            }

            $ids = MobilisasiOperasionalDetail::where('id_mo', $header->id)
                ->where('is_active', true)
                ->pluck('id_jadwal')
                ->all();

            $this->applyLegacyColumns($ids, $header->plat_mobil, $header->nama_driver);
        }
    }

    public function syncSnapshots(array $jadwalIds)
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
        foreach ($details as $detail) {
            $jadwal = $rows->get($detail->id_jadwal);
            if (!$jadwal) {
                continue;
            }

            $detail->sampler = $jadwal->sampler;
            $detail->no_quotation = $jadwal->no_quotation;
            $detail->nama_perusahaan = $jadwal->nama_perusahaan;
            $detail->jam_mulai = $jadwal->jam_mulai;
            $detail->jam_selesai = $jadwal->jam_selesai;
            $detail->wilayah = $jadwal->wilayah;
            $detail->durasi = $jadwal->durasi;
            $detail->updated_at = $now;
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
                $q->whereNull('driver')->orWhereRaw("TRIM(driver) = ''");
            })->where(function ($q) {
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
            ->where(function ($q) {
                $q->whereNull('driver')->orWhereRaw("TRIM(driver) = ''");
            })
            ->where(function ($q) {
                $q->whereNull('kendaraan')->orWhereRaw("TRIM(kendaraan) = ''");
            })
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
                        ];
                    })->values(),
                ];
            })
            ->values();
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
                'driver' => $mo->nama_driver,
                'kendaraan' => $mo->plat_mobil,
                'parsial' => null,
                'id_cabang' => null,
                'quotationKontrakH' => null,
                'quotationNonKontrak' => null,
            ];
        });

        $tim = $this->groupJadwalAsTim($jadwalRows, $mo->nama_driver);

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

    protected function fetchKendaraanAvailable($tanggal, $excludeMoId = null)
    {
        $url = 'https://apps.intilab.com/api/devices';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer RzBFAiAsSMLm1ORiB2hH9KQvaGjNSN-1jHrV7nK_WIf1cF4CnwIhAMjtQNnyRNrpy4NogP8qHJWdv_5KVyiWcTLVt7JKSsN2eyJ1IjozLCJlIjoiMjA2MC0wNy0wN1QxNzowMDowMC4wMDArMDA6MDAifQ',
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200 || !$response) {
            throw new Exception(
                'Gagal mengambil data kendaraan dari API.' . ($curlError ? ' ' . $curlError : ''),
                500
            );
        }

        $resBody = json_decode($response, true);
        $masterKendaraan = array_column(is_array($resBody) ? $resBody : [], 'name');

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
            'data' => array_map(function ($name) {
                return [
                    'id' => $name,
                    'text' => $name,
                    'plat_mobil' => $name,
                ];
            }, $masterKendaraan),
            'conflicts' => $detailKonflik,
        ];
    }

    protected function tablesReady()
    {
        return Schema::hasTable('mobilisasi_operasional')
            && Schema::hasTable('mobilisasi_operasional_detail');
    }
}
