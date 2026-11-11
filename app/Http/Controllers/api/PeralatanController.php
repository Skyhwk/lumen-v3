<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\KategoriPeralatanSampling;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class PeralatanController extends Controller
{
    public function index()
    {
        $data = KategoriPeralatanSampling::query()
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
            ]);

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

        $fromSampling = KategoriPeralatanSampling::query()
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
        $data = KategoriPeralatanSampling::query()
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
        if (empty($namaAlatList) || $jenisPeralatan === '' || $namaKategori === '' || $statusAlat === '' || $jumlahAlatTersedia < 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nama alat, jenis peralatan, kategori pengujian, status alat, dan jumlah alat tersedia wajib diisi',
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

        $duplicateNames = KategoriPeralatanSampling::query()
            ->where('is_active', true)
            ->whereIn('nama_alat', $namaAlatList)
            ->where('jenis_peralatan', $jenisPeralatan)
            ->where('warna', $request->warna)
            ->where('nama_kategori_pengujian', $namaKategori)
            ->where('id_cabang', $cabang->id)
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
                $row = KategoriPeralatanSampling::query()
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
                $row->updated_by = $this->karyawan;
                $row->updated_at = $now;
                $row->save();

                foreach ($namaAlatList as $extraNamaAlat) {
                    $extraRow = new KategoriPeralatanSampling();
                    $extraRow->nama_alat = $extraNamaAlat;
                    $extraRow->jenis_peralatan = $jenisPeralatan;
                    $extraRow->warna = $request->warna;
                    $extraRow->nama_kategori_pengujian = $namaKategori;
                    $extraRow->id_cabang = $cabang->id;
                    $extraRow->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $extraRow->status_alat = $statusAlat;
                    $extraRow->jumlah_alat_tersedia = $jumlahAlatTersedia;
                    $extraRow->created_by = $this->karyawan;
                    $extraRow->created_at = $now;
                    $extraRow->is_active = true;
                    $extraRow->save();
                }

                $message = 'Data kategori peralatan sampling berhasil diubah';
            } else {
                foreach ($namaAlatList as $namaAlat) {
                    $row = new KategoriPeralatanSampling();
                    $row->nama_alat = $namaAlat;
                    $row->jenis_peralatan = $jenisPeralatan;
                    $row->warna = $request->warna;
                    $row->nama_kategori_pengujian = $namaKategori;
                    $row->id_cabang = $cabang->id;
                    $row->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $row->status_alat = $statusAlat;
                    $row->jumlah_alat_tersedia = $jumlahAlatTersedia;
                    $row->created_by = $this->karyawan;
                    $row->created_at = $now;
                    $row->is_active = true;
                    $row->save();
                }

                $message = 'Data kategori peralatan sampling berhasil disimpan';
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
        $row = KategoriPeralatanSampling::query()
            ->where('is_active', true)
            ->find($request->id);

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Data kategori peralatan sampling tidak ditemukan',
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
}
