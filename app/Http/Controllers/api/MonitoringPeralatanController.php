<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\MonitoringPeralatan;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class MonitoringPeralatanController extends Controller
{
    public function index(Request $request)
    {
        $data = MonitoringPeralatan::query()
            ->where('is_active', true)
            ->select([
                'id',
                'nama_alat',
                'jenis_peralatan',
                'warna',
                'nama_kategori_pengujian',
                'id_cabang',
                'lokasi_penempatan_alat',
                'status_alat',
                'jumlah_alat_tersedia',
                'keterangan',
                'kode_aset'
            ]);

        $idCabang = $request->input('id_cabang');
        if ($idCabang !== null && $idCabang !== '' && ctype_digit((string) $idCabang)) {
            $data->where('id_cabang', (int) $idCabang);
        }

        if (!$this->requestHasOrderableColumn($request)) {
            $data->orderBy('jenis_peralatan')->orderBy('id');
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

        $fromSampling = MonitoringPeralatan::query()
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
        $data = MonitoringPeralatan::query()
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
        $counts = MonitoringPeralatan::query()
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

    public function store(Request $request)
    {
        $rawNamaAlat = $request->input('nama_alat');
        $namaAlatList = [];
        if (is_array($rawNamaAlat)) {
            foreach ($rawNamaAlat as $item) {
                $trimmed = trim((string) $item);
                if ($trimmed !== '' && !in_array($trimmed, $namaAlatList, true)) {
                    $namaAlatList[] = $trimmed;
                }
            }
        } elseif (is_string($rawNamaAlat) || is_numeric($rawNamaAlat)) {
            $trimmed = trim((string) $rawNamaAlat);
            if ($trimmed !== '') {
                $namaAlatList[] = $trimmed;
            }
        }

        $validator = Validator::make(
            array_merge($request->all(), ['nama_alat_list' => $namaAlatList]),
            [
                'nama_alat_list' => 'required|array|min:1',
                'nama_alat_list.*' => 'required|string|max:255',
                'jenis_peralatan' => 'required|string|max:255',
                'warna' => 'required|in:Merah,Biru,Hitam',
                'nama_kategori_pengujian' => 'required|string|max:70',
                'id_cabang' => 'required|integer',
                'status_alat' => 'required|string',
                'jumlah_alat_tersedia' => 'required|integer|min:0',
                'keterangan' => 'nullable|string',
                'kode_aset' => 'required|string|max:255',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $jenisPeralatan = trim((string) $request->jenis_peralatan);
        $namaKategori = trim((string) $request->nama_kategori_pengujian);
        $statusAlat = trim((string) $request->status_alat);
        $jumlahAlatTersedia = intval($request->jumlah_alat_tersedia);
        $keterangan = trim((string) $request->input('keterangan', ''));
        $keterangan = $keterangan === '' ? null : $keterangan;
        $kodeAset = trim((string) $request->input('kode_aset', ''));
        $kodeAset = $kodeAset === '' ? null : $kodeAset;
        if (empty($namaAlatList) || $jenisPeralatan === '' || $namaKategori === '' || $statusAlat === '' || $jumlahAlatTersedia < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nama alat, jenis peralatan, kategori pengujian, status alat, jumlah alat tersedia, dan kode aset wajib diisi',
            ], 422);
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

        $duplicateNames = MonitoringPeralatan::query()
            ->where('is_active', true)
            ->whereIn('nama_alat', $namaAlatList)
            ->where('jenis_peralatan', $jenisPeralatan)
            ->where('warna', $request->warna)
            ->where('nama_kategori_pengujian', $namaKategori)
            ->where('id_cabang', $cabang->id)
            ->where('kode_aset', $kodeAset)
            ->when($request->filled('id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->id);
            })
            ->pluck('nama_alat')
            ->all();

        if (!empty($duplicateNames)) {
            return response()->json([
                'success' => false,
                'message' => 'Nama alat ' . implode(', ', $duplicateNames) . ' dengan jenis, warna, kategori pengujian, dan lokasi yang sama sudah ada',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');

            if ($request->filled('id')) {
                $row = MonitoringPeralatan::query()
                    ->where('is_active', true)
                    ->find($request->id);

                if (!$row) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Data kategori peralatan sampling tidak ditemukan',
                    ], 404);
                }

                $firstNamaAlat = array_shift($namaAlatList);
                $row->nama_alat = $firstNamaAlat;
                $row->jenis_peralatan = $jenisPeralatan;
                $row->warna = $request->warna;
                $row->nama_kategori_pengujian = $namaKategori;
                $row->id_cabang = $cabang->id;
                $row->lokasi_penempatan_alat = $cabang->nama_cabang;
                $row->status_alat = $statusAlat;
                $row->jumlah_alat_tersedia = $jumlahAlatTersedia;
                $row->keterangan = $keterangan;
                $row->kode_aset = $kodeAset;
                $row->updated_by = $this->karyawan;
                $row->updated_at = $now;
                $row->save();

                foreach ($namaAlatList as $extraNamaAlat) {
                    $extraRow = new MonitoringPeralatan();
                    $extraRow->nama_alat = $extraNamaAlat;
                    $extraRow->jenis_peralatan = $jenisPeralatan;
                    $extraRow->warna = $request->warna;
                    $extraRow->nama_kategori_pengujian = $namaKategori;
                    $extraRow->id_cabang = $cabang->id;
                    $extraRow->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $extraRow->status_alat = $statusAlat;
                    $extraRow->jumlah_alat_tersedia = $jumlahAlatTersedia;
                    $extraRow->keterangan = $keterangan;
                    $extraRow->kode_aset = $kodeAset;
                    $extraRow->created_by = $this->karyawan;
                    $extraRow->created_at = $now;
                    $extraRow->is_active = true;
                    $extraRow->save();
                }

                $message = 'Data monitoring peralatan berhasil diubah';
            } else {
                foreach ($namaAlatList as $namaAlat) {
                    $row = new MonitoringPeralatan();
                    $row->nama_alat = $namaAlat;
                    $row->jenis_peralatan = $jenisPeralatan;
                    $row->warna = $request->warna;
                    $row->nama_kategori_pengujian = $namaKategori;
                    $row->id_cabang = $cabang->id;
                    $row->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $row->status_alat = $statusAlat;
                    $row->jumlah_alat_tersedia = $jumlahAlatTersedia;
                    $row->keterangan = $keterangan;
                    $row->kode_aset = $kodeAset;
                    $row->created_by = $this->karyawan;
                    $row->created_at = $now;
                    $row->is_active = true;
                    $row->save();
                }

                $message = 'Data monitoring peralatan berhasil disimpan';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
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
        $row = MonitoringPeralatan::query()
            ->where('is_active', true)
            ->find($request->id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data monitoring peralatan tidak ditemukan',
            ], 404);
        }

        $row->is_active = false;
        $row->deleted_at = date('Y-m-d H:i:s');
        $row->deleted_by = $this->karyawan;
        $row->save();

        return response()->json([
            'success' => true,
            'message' => 'Data kategori peralatan sampling berhasil dihapus',
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
