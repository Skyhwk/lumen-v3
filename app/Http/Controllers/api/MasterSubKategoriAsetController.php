<?php

namespace App\Http\Controllers\api;

use App\Models\MasterSubKategoriAset;
use App\Models\MasterKategoriAset;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class MasterSubKategoriAsetController extends Controller
{
    public function index()
    {
        $data = MasterSubKategoriAset::with('kategori')
            ->where('is_active', true);

        return Datatables::of($data)
            ->addColumn('nama_kategori', function ($data) {
                return $data->kategori->nama_kategori;
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        $kategori = MasterSubKategoriAset::create([
            'nama_sub_kategori' => $request->nama_sub_kategori,
            'id_kategori' => $request->id_kategori,
            'created_at'    => Carbon::now(),
            'created_by'    => $this->karyawan
        ]);

        return response()->json([
            'message' => "Kategori aset $request->nama_sub_kategori berhasil dibuat",
            'data'    => $kategori
        ], 201);
    }

    public function update(Request $request)
    {
        $kategori = MasterSubKategoriAset::findOrFail($request->id);
        $kategori->update([
            'nama_sub_kategori' => $request->nama_sub_kategori,
            'id_kategori' => $request->id_kategori,
            'updated_at'    => Carbon::now(),
            'updated_by'    => $this->karyawan
        ]);

        return response()->json([
            'message' => "Kategori aset $kategori->nama_sub_kategori berhasil diupdate",
            'data'    => $kategori
        ]);
    }

    public function delete(Request $request)
    {
        $kategori = MasterSubKategoriAset::findOrFail($request->id);
        $kategori->update([
            'is_active' => false,
            'deleted_at' => Carbon::now(),
            'deleted_by' => $this->karyawan]
        );

        return response()->json([
            'message' => 'Kategori aset berhasil di-nonaktifkan'
        ]);
    }
}