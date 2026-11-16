<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MonitoringPeralatan;
use App\Models\Peralatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class PeralatanController extends Controller
{
    public function index()
    {
        $table = (new Peralatan())->getTable();
        $monitoringTable = (new MonitoringPeralatan())->getTable();

        $latestMonitoring = DB::table($monitoringTable)
            ->select('id_peralatan', DB::raw('MAX(id) as latest_id'))
            ->where('is_active', true)
            ->whereNotNull('id_peralatan')
            ->groupBy('id_peralatan');

        $data = Peralatan::query()
            ->from("{$table} as p")
            ->leftJoinSub($latestMonitoring, 'latest_mp', function ($join) {
                $join->on('latest_mp.id_peralatan', '=', 'p.id');
            })
            ->leftJoin("{$monitoringTable} as mp", 'mp.id', '=', 'latest_mp.latest_id')
            ->where('p.is_active', true)
            ->select([
                'p.id',
                'p.nama_alat',
                'p.kode_aset',
                'p.jenis_peralatan',
                'p.warna',
                'p.nama_kategori_pengujian',
                'p.id_cabang',
                'p.lokasi_penempatan_alat',
                'mp.status_alat',
                'mp.keterangan',
                'mp.start_date',
                'mp.end_date',
            ])
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS p2
                    LEFT JOIN (
                        SELECT id_peralatan, MAX(id) AS latest_id
                        FROM {$monitoringTable}
                        WHERE is_active = 1
                          AND id_peralatan IS NOT NULL
                        GROUP BY id_peralatan
                    ) AS lm2 ON lm2.id_peralatan = p2.id
                    LEFT JOIN {$monitoringTable} AS mp2 ON mp2.id = lm2.latest_id
                    WHERE p2.is_active = 1
                      AND p2.nama_alat = p.nama_alat
                      AND p2.jenis_peralatan <=> p.jenis_peralatan
                      AND p2.nama_kategori_pengujian <=> p.nama_kategori_pengujian
                      AND p2.warna <=> p.warna
                      AND p2.lokasi_penempatan_alat <=> p.lokasi_penempatan_alat
                      AND mp2.status_alat = ?
                ) AS jumlah_alat_tersedia",
                ['Tersedia']
            )
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS p3
                    LEFT JOIN (
                        SELECT id_peralatan, MAX(id) AS latest_id
                        FROM {$monitoringTable}
                        WHERE is_active = 1
                          AND id_peralatan IS NOT NULL
                        GROUP BY id_peralatan
                    ) AS lm3 ON lm3.id_peralatan = p3.id
                    LEFT JOIN {$monitoringTable} AS mp3 ON mp3.id = lm3.latest_id
                    WHERE p3.is_active = 1
                      AND p3.nama_alat = p.nama_alat
                      AND p3.jenis_peralatan <=> p.jenis_peralatan
                      AND p3.nama_kategori_pengujian <=> p.nama_kategori_pengujian
                      AND p3.warna <=> p.warna
                      AND p3.lokasi_penempatan_alat <=> p.lokasi_penempatan_alat
                      AND (mp3.status_alat IS NULL OR mp3.status_alat <> ?)
                ) AS jumlah_alat_belum_tersedia",
                ['Tersedia']
            )
            ->orderBy('p.nama_alat')
            ->orderBy('p.jenis_peralatan')
            ->orderBy('p.nama_kategori_pengujian')
            ->orderBy('p.warna')
            ->orderBy('p.lokasi_penempatan_alat')
            ->orderBy('p.kode_aset')
            ->orderBy('p.id');

        return Datatables::of($data)->make(true);
    }

    public function getNames()
    {
        $data = [];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Nama peralatan sampling berhasil diambil',
        ]);
    }

    public function getKategori()
    {
        $fromMaster = MasterKategori::query()
            ->where('is_active', true)
            ->whereNotNull('nama_kategori')
            ->whereRaw("TRIM(nama_kategori) <> ''")
            ->pluck('nama_kategori');

        $fromSampling = Peralatan::query()
            ->where('is_active', true)
            ->whereNotNull('nama_kategori_pengujian')
            ->whereRaw("TRIM(nama_kategori_pengujian) <> ''")
            ->pluck('nama_kategori_pengujian');

        $data = $fromMaster
            ->merge($fromSampling)
            ->map(function ($name) {
                return trim((string) $name);
            })
            ->filter()
            ->unique(function ($name) {
                return mb_strtolower($name);
            })
            ->sort(function ($left, $right) {
                return strcasecmp($left, $right);
            })
            ->values()
            ->map(function ($name) {
                return ['nama_kategori' => $name];
            });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Kategori pengujian berhasil diambil',
        ]);
    }

    public function getJenis()
    {
        $data = Peralatan::query()
            ->where('is_active', true)
            ->whereNotNull('jenis_peralatan')
            ->whereRaw("TRIM(jenis_peralatan) <> ''")
            ->selectRaw('TRIM(jenis_peralatan) as jenis_peralatan')
            ->distinct()
            ->orderBy('jenis_peralatan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Jenis peralatan berhasil diambil',
        ]);
    }

    public function getCabang()
    {
        $data = MasterCabang::query()
            ->where('is_active', true)
            ->whereNotNull('nama_cabang')
            ->whereRaw("TRIM(nama_cabang) <> ''")
            ->orderBy('nama_cabang')
            ->get(['id', 'nama_cabang']);

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Lokasi penempatan alat berhasil diambil',
        ]);
    }

    public function store(Request $request)
    {
        $namaAlat = trim((string) $request->input('nama_alat', ''));
        $jenisPeralatan = trim((string) $request->input('jenis_peralatan', ''));
        $namaKategori = trim((string) $request->input('nama_kategori_pengujian', ''));
        $rawDetails = $request->input('details', []);
        if (!is_array($rawDetails)) {
            $rawDetails = [];
        }

        $isUpdate = $request->filled('id');
        $details = [];
        $kodeSeen = [];
        foreach ($rawDetails as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kodeAset = trim((string) ($item['kode_aset'] ?? $item['kode_alat'] ?? ''));
            $statusAlat = trim((string) ($item['status_alat'] ?? ''));
            if ($kodeAset === '') {
                continue;
            }
            if (!$isUpdate && $statusAlat === '') {
                continue;
            }

            $kodeKey = mb_strtolower($kodeAset);
            if (isset($kodeSeen[$kodeKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode aset "' . $kodeAset . '" duplikat di form',
                ], 422);
            }
            $kodeSeen[$kodeKey] = true;

            $details[] = [
                'kode_aset' => $kodeAset,
                'status_alat' => $statusAlat === '' ? null : $statusAlat,
                'keterangan' => trim((string) ($item['keterangan'] ?? '')),
                'start_date' => $this->normalizeDate($item['start_date'] ?? null),
                'end_date' => $this->normalizeDate($item['end_date'] ?? null),
            ];
        }

        $rules = [
            'nama_alat' => 'required|string|max:255',
            'jenis_peralatan' => 'required|string|max:255',
            'warna' => 'required|in:Merah,Biru,Hitam',
            'nama_kategori_pengujian' => 'required|string|max:70',
            'id_cabang' => 'required|integer',
            'details' => 'required|array|min:1',
            'details.*.kode_aset' => 'required|string|max:255',
            'details.*.keterangan' => 'nullable|string',
            'details.*.start_date' => 'nullable|date',
            'details.*.end_date' => 'nullable|date',
        ];
        if (!$isUpdate) {
            $rules['details.*.status_alat'] = 'required|in:' . implode(',', MonitoringPeralatan::STATUS_ALAT);
        } else {
            $rules['details.*.status_alat'] = 'nullable|in:' . implode(',', MonitoringPeralatan::STATUS_ALAT);
        }

        $validator = Validator::make(
            array_merge($request->all(), [
                'nama_alat' => $namaAlat,
                'jenis_peralatan' => $jenisPeralatan,
                'nama_kategori_pengujian' => $namaKategori,
                'details' => $details,
            ]),
            $rules
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if ($namaAlat === '' || $jenisPeralatan === '' || $namaKategori === '' || empty($details)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama alat, jenis peralatan, kategori pengujian, dan minimal satu detail kode aset wajib diisi',
            ], 422);
        }

        if (!$isUpdate) {
            foreach ($details as $index => $detail) {
                $needsPeriod = MonitoringPeralatan::requiresPeriod($detail['status_alat'] ?? null);
                if ($needsPeriod && (empty($detail['start_date']) || empty($detail['end_date']))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tanggal mulai dan berakhir wajib diisi untuk status maintenance/kalibrasi (detail ke-' . ($index + 1) . ')',
                    ], 422);
                }
                if (!$needsPeriod) {
                    $details[$index]['start_date'] = null;
                    $details[$index]['end_date'] = null;
                }
            }
        }

        $cabang = MasterCabang::query()
            ->where('is_active', true)
            ->where('id', $request->id_cabang)
            ->first(['id', 'nama_cabang']);

        if (!$cabang) {
            return response()->json([
                'success' => false,
                'message' => 'Lokasi penempatan alat tidak ditemukan',
            ], 404);
        }

        $kodeList = array_column($details, 'kode_aset');
        $duplicateQuery = Peralatan::query()
            ->where('is_active', true)
            ->whereIn('kode_aset', $kodeList)
            ->when($request->filled('id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->id);
            });

        $duplicateKode = $duplicateQuery->pluck('kode_aset')->all();
        if (!empty($duplicateKode)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode aset ' . implode(', ', $duplicateKode) . ' sudah terdaftar',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $savedCount = 0;

            if ($isUpdate) {
                $row = Peralatan::query()
                    ->where('is_active', true)
                    ->find($request->id);

                if (!$row) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Data peralatan tidak ditemukan',
                    ], 404);
                }

                $firstDetail = $details[0];
                $this->fillPeralatanMaster($row, $namaAlat, $jenisPeralatan, $namaKategori, $cabang, $request->warna, $firstDetail['kode_aset']);
                $row->updated_by = $this->karyawan;
                $row->updated_at = $now;
                $row->save();
                $savedCount = 1;

                $message = 'Data peralatan berhasil diubah';
            } else {
                foreach ($details as $detail) {
                    $row = new Peralatan();
                    $this->fillPeralatanMaster($row, $namaAlat, $jenisPeralatan, $namaKategori, $cabang, $request->warna, $detail['kode_aset']);
                    $row->created_by = $this->karyawan;
                    $row->created_at = $now;
                    $row->is_active = true;
                    $row->save();
                    $this->createMonitoringHistory($row->id, $detail, $now);
                    $savedCount++;
                }

                $message = 'Data peralatan berhasil disimpan (' . $savedCount . ' kode aset)';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'saved_count' => $savedCount,
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $row = Peralatan::query()
            ->where('is_active', true)
            ->find($request->id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data peralatan tidak ditemukan',
            ], 404);
        }

        $now = date('Y-m-d H:i:s');
        $row->is_active = false;
        $row->deleted_at = $now;
        $row->deleted_by = $this->karyawan;
        $row->save();

        if (Schema::hasTable('monitoring_peralatan')) {
            MonitoringPeralatan::query()
                ->where('id_peralatan', $row->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'deleted_at' => $now,
                    'deleted_by' => $this->karyawan,
                    'updated_at' => $now,
                    'updated_by' => $this->karyawan,
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data peralatan berhasil dihapus',
        ]);
    }

    private function fillPeralatanMaster(
        Peralatan $row,
        string $namaAlat,
        string $jenisPeralatan,
        string $namaKategori,
        MasterCabang $cabang,
        $warna,
        string $kodeAset
    ): void {
        $row->nama_alat = $namaAlat;
        $row->kode_aset = $kodeAset;
        $row->jenis_peralatan = $jenisPeralatan;
        $row->warna = $warna;
        $row->nama_kategori_pengujian = $namaKategori;
        $row->id_cabang = $cabang->id;
        $row->lokasi_penempatan_alat = $cabang->nama_cabang;
    }

    private function createMonitoringHistory(int $idPeralatan, array $detail, string $now): void
    {
        $history = new MonitoringPeralatan();
        $history->id_peralatan = $idPeralatan;
        $history->status_alat = $detail['status_alat'];
        $history->keterangan = $detail['keterangan'] !== '' ? $detail['keterangan'] : null;
        $history->start_date = $detail['start_date'];
        $history->end_date = $detail['end_date'];
        $history->is_active = true;
        $history->created_by = $this->karyawan;
        $history->created_at = $now;
        $history->save();
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 20000000000) {
                $timestamp = (int) floor($timestamp / 1000);
            }
            return date('Y-m-d', $timestamp);
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
