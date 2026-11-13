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
        $table = (new KategoriPeralatanSampling())->getTable();

        $data = KategoriPeralatanSampling::query()
            ->where("{$table}.is_active", true)
            ->select([
                "{$table}.id",
                "{$table}.nama_alat",
                "{$table}.kode_alat",
                "{$table}.jenis_peralatan",
                "{$table}.warna",
                "{$table}.nama_kategori_pengujian",
                "{$table}.id_cabang",
                "{$table}.lokasi_penempatan_alat",
                "{$table}.status_alat",
            ])
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS k2
                    WHERE k2.is_active = 1
                      AND k2.nama_alat = {$table}.nama_alat
                      AND k2.jenis_peralatan <=> {$table}.jenis_peralatan
                      AND k2.nama_kategori_pengujian <=> {$table}.nama_kategori_pengujian
                      AND k2.warna <=> {$table}.warna
                      AND k2.lokasi_penempatan_alat <=> {$table}.lokasi_penempatan_alat
                      AND k2.status_alat = ?
                ) AS jumlah_alat_tersedia",
                ['Tersedia']
            )
            ->selectRaw(
                "(
                    SELECT COUNT(*)
                    FROM {$table} AS k3
                    WHERE k3.is_active = 1
                      AND k3.nama_alat = {$table}.nama_alat
                      AND k3.jenis_peralatan <=> {$table}.jenis_peralatan
                      AND k3.nama_kategori_pengujian <=> {$table}.nama_kategori_pengujian
                      AND k3.warna <=> {$table}.warna
                      AND k3.lokasi_penempatan_alat <=> {$table}.lokasi_penempatan_alat
                      AND (k3.status_alat IS NULL OR k3.status_alat <> ?)
                ) AS jumlah_alat_belum_tersedia",
                ['Tersedia']
            )
            ->orderBy("{$table}.nama_alat")
            ->orderBy("{$table}.jenis_peralatan")
            ->orderBy("{$table}.nama_kategori_pengujian")
            ->orderBy("{$table}.warna")
            ->orderBy("{$table}.lokasi_penempatan_alat")
            ->orderBy("{$table}.kode_alat")
            ->orderBy("{$table}.id");

        return Datatables::of($data)->make(true);
    }

    public function getNames()
    {
        // $data = GaAsset::query()
        //     ->join('master_sub_kategori_aset as s', 's.id', '=', 'ga_assets.sub_kategori_aset_id')
        //     ->where('ga_assets.is_active', true)
        //     ->where('ga_assets.record_kind', GaAsset::RECORD_UNIT)
        //     ->where('ga_assets.condition', 'normal')
        //     ->whereRaw("UPPER(TRIM(s.nama_sub_kategori)) = 'SAMPLING'")
        //     ->whereNotNull('ga_assets.name')
        //     ->whereRaw("TRIM(ga_assets.name) <> ''")
        //     ->selectRaw('TRIM(ga_assets.name) as name')
        //     ->distinct()
        //     ->orderBy('name')
        //     ->get();

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
        $namaAlat = trim((string) $request->input('nama_alat', ''));
        $jenisPeralatan = trim((string) $request->input('jenis_peralatan', ''));
        $namaKategori = trim((string) $request->input('nama_kategori_pengujian', ''));
        $rawDetails = $request->input('details', []);
        if (!is_array($rawDetails)) {
            $rawDetails = [];
        }

        $details = [];
        $kodeSeen = [];
        foreach ($rawDetails as $item) {
            if (!is_array($item)) {
                continue;
            }

            $kodeAlat = trim((string) ($item['kode_alat'] ?? ''));
            $statusAlat = trim((string) ($item['status_alat'] ?? ''));
            if ($kodeAlat === '' || $statusAlat === '') {
                continue;
            }

            $kodeKey = mb_strtolower($kodeAlat);
            if (isset($kodeSeen[$kodeKey])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kode alat "' . $kodeAlat . '" duplikat di form',
                ], 422);
            }
            $kodeSeen[$kodeKey] = true;

            $details[] = [
                'kode_alat' => $kodeAlat,
                'status_alat' => $statusAlat,
            ];
        }

        $validator = Validator::make(
            array_merge($request->all(), [
                'nama_alat' => $namaAlat,
                'jenis_peralatan' => $jenisPeralatan,
                'nama_kategori_pengujian' => $namaKategori,
                'details' => $details,
            ]),
            [
                'nama_alat' => 'required|string|max:255',
                'jenis_peralatan' => 'required|string|max:255',
                'warna' => 'required|in:Merah,Biru,Hitam',
                'nama_kategori_pengujian' => 'required|string|max:70',
                'id_cabang' => 'required|integer',
                'details' => 'required|array|min:1',
                'details.*.kode_alat' => 'required|string',
                'details.*.status_alat' => 'required|string',
            ]
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
                'message' => 'Nama alat, jenis peralatan, kategori pengujian, dan minimal satu detail kode alat wajib diisi',
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

        $kodeList = array_column($details, 'kode_alat');
        $duplicateQuery = KategoriPeralatanSampling::query()
            ->where('is_active', true)
            ->where('nama_alat', $namaAlat)
            ->where('jenis_peralatan', $jenisPeralatan)
            ->where('warna', $request->warna)
            ->where('nama_kategori_pengujian', $namaKategori)
            ->where('id_cabang', $cabang->id)
            ->whereIn('kode_alat', $kodeList)
            ->when($request->filled('id'), function ($query) use ($request) {
                $query->where('id', '!=', $request->id);
            });

        $duplicateKode = $duplicateQuery->pluck('kode_alat')->all();
        if (!empty($duplicateKode)) {
            return response()->json([
                'success' => false,
                'message' => 'Kode alat ' . implode(', ', $duplicateKode) . ' dengan nama, jenis, warna, kategori, dan lokasi yang sama sudah ada',
            ], 400);
        }

        DB::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $savedCount = 0;

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

                $firstDetail = array_shift($details);
                $row->nama_alat = $namaAlat;
                $row->kode_alat = $firstDetail['kode_alat'];
                $row->jenis_peralatan = $jenisPeralatan;
                $row->warna = $request->warna;
                $row->nama_kategori_pengujian = $namaKategori;
                $row->id_cabang = $cabang->id;
                $row->lokasi_penempatan_alat = $cabang->nama_cabang;
                $row->status_alat = $firstDetail['status_alat'];
                $row->updated_by = $this->karyawan;
                $row->updated_at = $now;
                $row->save();
                $savedCount++;

                foreach ($details as $detail) {
                    $extraRow = new KategoriPeralatanSampling();
                    $extraRow->nama_alat = $namaAlat;
                    $extraRow->kode_alat = $detail['kode_alat'];
                    $extraRow->jenis_peralatan = $jenisPeralatan;
                    $extraRow->warna = $request->warna;
                    $extraRow->nama_kategori_pengujian = $namaKategori;
                    $extraRow->id_cabang = $cabang->id;
                    $extraRow->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $extraRow->status_alat = $detail['status_alat'];
                    $extraRow->created_by = $this->karyawan;
                    $extraRow->created_at = $now;
                    $extraRow->is_active = true;
                    $extraRow->save();
                    $savedCount++;
                }

                $message = 'Data kategori peralatan sampling berhasil diubah (' . $savedCount . ' baris)';
            } else {
                foreach ($details as $detail) {
                    $row = new KategoriPeralatanSampling();
                    $row->nama_alat = $namaAlat;
                    $row->kode_alat = $detail['kode_alat'];
                    $row->jenis_peralatan = $jenisPeralatan;
                    $row->warna = $request->warna;
                    $row->nama_kategori_pengujian = $namaKategori;
                    $row->id_cabang = $cabang->id;
                    $row->lokasi_penempatan_alat = $cabang->nama_cabang;
                    $row->status_alat = $detail['status_alat'];
                    $row->created_by = $this->karyawan;
                    $row->created_at = $now;
                    $row->is_active = true;
                    $row->save();
                    $savedCount++;
                }

                $message = 'Data kategori peralatan sampling berhasil disimpan (' . $savedCount . ' baris)';
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
