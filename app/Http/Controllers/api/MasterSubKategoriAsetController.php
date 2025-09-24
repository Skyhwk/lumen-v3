<?php

namespace App\Http\Controllers\api;

use App\Models\MasterSubKategoriAset;
use App\Models\MasterKategoriAset;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $data = MasterSubKategoriAset::where('is_active', true);

        return Datatables::of($data)->make(true);
    }

    public function store(Request $request)
    {
        $kategori = MasterSubKategoriAset::create([
            'nama_kategori' => $request->nama_kategori,
            'kategori_aset_id' => $request->kategori_aset_id,
            'created_at'    => Carbon::now(),
            'created_by'    => $this->karyawan
        ]);

        return response()->json([
            'message' => "Kategori aset $request->nama_kategori berhasil dibuat",
            'data'    => $kategori
        ], 201);
    }

    public function update(Request $request)
    {
        $kategori = MasterSubKategoriAset::findOrFail($request->id);
        $kategori->update([
            'nama_kategori' => $request->nama_kategori,
            'kategori_aset_id' => $request->kategori_aset_id,
            'updated_at'    => Carbon::now(),
            'updated_by'    => $this->karyawan
        ]);

        return response()->json([
            'message' => "Kategori aset $kategori->nama_kategori berhasil diupdate",
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