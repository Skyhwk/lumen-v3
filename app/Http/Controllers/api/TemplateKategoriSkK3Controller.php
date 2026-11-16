<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\MasterSubKategori;
use App\Models\TemplateKategoriSkK3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Datatables\Datatables;

class TemplateKategoriSkK3Controller extends Controller
{
    public function index()
    {
        try {
            $data = TemplateKategoriSkK3::query()
                ->where('template_kategori_sk_k3.is_active', true)
                ->join('master_sub_kategori as msk', 'msk.id', '=', 'template_kategori_sk_k3.id_sub_kategori')
                ->when(Schema::hasColumn('master_sub_kategori', 'is_active'), function ($query) {
                    $query->where('msk.is_active', 1);
                })
                ->select([
                    'template_kategori_sk_k3.id',
                    'template_kategori_sk_k3.id_sub_kategori',
                    'template_kategori_sk_k3.nama_kategori',
                    'template_kategori_sk_k3.nama_sub_kategori',
                    'msk.id_kategori',
                    'template_kategori_sk_k3.created_by',
                    'template_kategori_sk_k3.created_at',
                ]);

            return Datatables::of($data)->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    public function getKategori(Request $request)
    {
        $query = MasterKategori::query()
            ->select('id', 'nama_kategori')
            ->orderBy('nama_kategori');

        if (Schema::hasColumn('master_kategori', 'is_active')) {
            $query->where('is_active', 1);
        }

        return response()->json($query->get(), 200);
    }

    public function getSubKategoriOptions(Request $request)
    {
        $query = MasterSubKategori::query()->orderBy('nama_sub_kategori');
        if (Schema::hasColumn('master_sub_kategori', 'is_active')) {
            $query->where('is_active', 1);
        }
        if ($request->id_kategori) {
            $query->where('id_kategori', (int) $request->id_kategori);
        }

        $data = $query->get(['id', 'id_kategori', 'nama_kategori', 'nama_sub_kategori']);

        return response()->json($data, 200);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $subId = (int) $request->id_sub_kategori;
            if ($subId < 1) {
                return response()->json(['message' => 'Sub kategori wajib dipilih'], 422);
            }

            $subKategori = MasterSubKategori::find($subId);
            if (!$subKategori) {
                return response()->json(['message' => 'Sub kategori tidak ditemukan'], 404);
            }
            if (Schema::hasColumn('master_sub_kategori', 'is_active') && (int) $subKategori->is_active !== 1) {
                return response()->json(['message' => 'Sub kategori tidak aktif'], 422);
            }

            $parentKategori = null;
            if ($subKategori->id_kategori) {
                $parentQuery = MasterKategori::query()->where('id', $subKategori->id_kategori);
                if (Schema::hasColumn('master_kategori', 'is_active')) {
                    $parentQuery->where('is_active', 1);
                }
                $parentKategori = $parentQuery->first(['id', 'nama_kategori']);
            }

            $namaKategori = $parentKategori->nama_kategori
                ?? $subKategori->nama_kategori
                ?? '';

            if (!$request->id) {
                $exists = TemplateKategoriSkK3::where('id_sub_kategori', $subId)
                    ->where('is_active', true)
                    ->exists();
                if ($exists) {
                    return response()->json(['message' => 'Sub kategori sudah ada di template'], 422);
                }

                TemplateKategoriSkK3::create([
                    'id_sub_kategori' => $subId,
                    'nama_kategori' => $namaKategori,
                    'nama_sub_kategori' => $subKategori->nama_sub_kategori,
                    'created_by' => $this->karyawan,
                    'created_at' => date('Y-m-d H:i:s'),
                    'is_active' => 1,
                ]);
            } else {
                $row = TemplateKategoriSkK3::find($request->id);
                if (!$row) {
                    return response()->json(['message' => 'Data tidak ditemukan'], 404);
                }

                $duplicate = TemplateKategoriSkK3::where('id_sub_kategori', $subId)
                    ->where('is_active', true)
                    ->where('id', '!=', $row->id)
                    ->exists();
                if ($duplicate) {
                    return response()->json(['message' => 'Sub kategori sudah ada di template'], 422);
                }

                $row->id_sub_kategori = $subId;
                $row->nama_kategori = $namaKategori;
                $row->nama_sub_kategori = $subKategori->nama_sub_kategori;
                $row->updated_by = $this->karyawan;
                $row->updated_at = date('Y-m-d H:i:s');
                $row->save();
            }

            DB::commit();
            return response()->json(['message' => 'Data telah disimpan'], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json(['message' => $th->getMessage()], 500);
        }
    }

    public function delete(Request $request)
    {
        $row = TemplateKategoriSkK3::find($request->id);
        if (!$row) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $row->is_active = false;
        $row->deleted_by = $this->karyawan;
        $row->deleted_at = date('Y-m-d H:i:s');
        $row->save();

        return response()->json(['message' => 'Data berhasil dihapus'], 201);
    }
}