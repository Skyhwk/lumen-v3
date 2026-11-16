<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MonitoringPeralatan;
use App\Models\Peralatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class MonitoringPeralatanController extends Controller
{
    public function index(Request $request)
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
                'p.id as id_peralatan',
                'mp.id as id_monitoring',
                'p.nama_alat',
                'p.jenis_peralatan',
                'p.warna',
                'p.nama_kategori_pengujian',
                'p.id_cabang',
                'p.lokasi_penempatan_alat',
                'p.kode_aset',
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
            );

        $idCabang = $request->input('id_cabang');
        if ($idCabang !== null && $idCabang !== '' && ctype_digit((string) $idCabang)) {
            $data->where('p.id_cabang', (int) $idCabang);
        }

        if (!$this->requestHasOrderableColumn($request)) {
            $data->orderBy('p.jenis_peralatan')
                ->orderBy('p.nama_kategori_pengujian')
                ->orderBy('p.nama_alat')
                ->orderBy('p.warna')
                ->orderBy('p.kode_aset')
                ->orderBy('p.id');
        }

        return Datatables::of($data)->make(true);
    }

    public function getNames()
    {
        $data = GaAsset::query()
            ->join('master_sub_kategori_aset as s', 's.id', '=', 'ga_assets.sub_kategori_aset_id')
            ->where('ga_assets.is_active', true)
            ->where('ga_assets.record_kind', GaAsset::RECORD_UNIT)
            ->where('ga_assets.condition', 'normal')
            ->whereRaw("UPPER(TRIM(s.nama_sub_kategori)) = 'SAMPLING'")
            ->whereNotNull('ga_assets.name')
            ->whereRaw("TRIM(ga_assets.name) <> ''")
            ->selectRaw('TRIM(ga_assets.name) as name')
            ->distinct()
            ->orderBy('name')
            ->get();

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
        $counts = Peralatan::query()
            ->where('is_active', true)
            ->select('id_cabang', DB::raw('COUNT(*) as total'))
            ->groupBy('id_cabang')
            ->pluck('total', 'id_cabang');

        $data = MasterCabang::query()
            ->where('is_active', true)
            ->whereNotNull('nama_cabang')
            ->whereRaw("TRIM(nama_cabang) <> ''")
            ->orderBy('nama_cabang')
            ->get(['id', 'nama_cabang'])
            ->map(function ($cabang) use ($counts) {
                $cabang->total = (int) ($counts[$cabang->id] ?? 0);
                return $cabang;
            });

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Lokasi penempatan alat berhasil diambil',
        ]);
    }

    public function getHistory(Request $request)
    {
        $idPeralatan = (int) ($request->input('id_peralatan') ?: $request->input('id'));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 10;

        if ($idPeralatan <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'ID peralatan wajib diisi',
            ], 422);
        }

        $peralatan = Peralatan::query()
            ->where('is_active', true)
            ->find($idPeralatan, [
                'id',
                'nama_alat',
                'kode_aset',
                'jenis_peralatan',
                'warna',
                'nama_kategori_pengujian',
                'lokasi_penempatan_alat',
            ]);

        if (!$peralatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data peralatan tidak ditemukan',
            ], 404);
        }

        $query = MonitoringPeralatan::query()
            ->where('is_active', true)
            ->where('id_peralatan', $idPeralatan)
            ->orderByDesc('id');

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $rows = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get([
                'id',
                'status_alat',
                'keterangan',
                'start_date',
                'end_date',
                'created_by',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'peralatan' => $peralatan,
                'rows' => $rows,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ],
            'message' => 'Histori status peralatan berhasil diambil',
        ]);
    }

    public function store(Request $request)
    {
        $idPeralatan = (int) ($request->input('id_peralatan') ?: $request->input('id'));
        $statusAlat = trim((string) $request->input('status_alat', ''));
        $keterangan = trim((string) $request->input('keterangan', ''));
        $keterangan = $keterangan === '' ? null : $keterangan;
        $startDate = $this->normalizeDate($request->input('start_date'));
        $endDate = $this->normalizeDate($request->input('end_date'));

        $validator = Validator::make(
            [
                'id_peralatan' => $idPeralatan,
                'status_alat' => $statusAlat,
                'keterangan' => $keterangan,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            [
                'id_peralatan' => 'required|integer|min:1',
                'status_alat' => 'required|string',
                'keterangan' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $needsPeriod = in_array(mb_strtolower($statusAlat), [
            'sedang di maintenance',
            'sedang di kalibrasi',
        ], true);

        if ($needsPeriod && (!$startDate || !$endDate)) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal mulai dan berakhir wajib diisi untuk status maintenance/kalibrasi',
            ], 422);
        }

        if (!$needsPeriod) {
            $startDate = null;
            $endDate = null;
        }

        $peralatan = Peralatan::query()
            ->where('is_active', true)
            ->find($idPeralatan);

        if (!$peralatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data peralatan tidak ditemukan',
            ], 404);
        }

        try {
            $now = date('Y-m-d H:i:s');
            $history = new MonitoringPeralatan();
            $history->id_peralatan = $peralatan->id;
            $history->status_alat = $statusAlat;
            $history->keterangan = $keterangan;
            $history->start_date = $startDate;
            $history->end_date = $endDate;
            $history->is_active = true;
            $history->created_by = $this->karyawan;
            $history->created_at = $now;
            $history->save();

            return response()->json([
                'success' => true,
                'message' => 'Status peralatan berhasil diperbarui',
                'data' => [
                    'id' => $peralatan->id,
                    'id_peralatan' => $peralatan->id,
                    'id_monitoring' => $history->id,
                    'status_alat' => $history->status_alat,
                    'keterangan' => $history->keterangan,
                    'start_date' => $history->start_date,
                    'end_date' => $history->end_date,
                ],
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
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

    public function delete(Request $request)
    {
        $row = Peralatan::query()
            ->where('is_active', true)
            ->find($request->id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data monitoring peralatan tidak ditemukan',
            ], 404);
        }

        $now = date('Y-m-d H:i:s');
        $row->is_active = false;
        $row->deleted_at = $now;
        $row->deleted_by = $this->karyawan;
        $row->updated_at = $now;
        $row->updated_by = $this->karyawan;
        $row->save();

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

        return response()->json([
            'success' => true,
            'message' => 'Data monitoring peralatan berhasil dihapus',
        ]);
    }

    private function requestHasOrderableColumn(Request $request): bool
    {
        $orders = $request->input('order', []);
        if (!is_array($orders)) {
            return false;
        }

        foreach ($orders as $order) {
            $index = isset($order['column']) ? (int) $order['column'] : -1;
            if ($index < 0) {
                continue;
            }

            $orderable = $request->input("columns.$index.orderable", 'true');
            $name = $request->input("columns.$index.name") ?: $request->input("columns.$index.data");
            if (($orderable === true || $orderable === 'true') && is_string($name) && $name !== '') {
                return true;
            }
        }

        return false;
    }
}
