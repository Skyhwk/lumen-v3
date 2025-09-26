<?php

namespace App\Http\Controllers\api;

use App\Models\PengesahanLhp;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class PengesahanLhpController extends Controller
{
    public function index()
    {
        $now = carbon::now(); // waktu sekarang
        $data = PengesahanLhp::all();

        // cari tanggal mulai paling besar yg <= now
        $latest = $data
            ->where('berlaku_mulai', '<=', $now)
            ->sortByDesc('berlaku_mulai')
            ->first();

        return Datatables::of($data)
            ->addColumn('is_active', function ($row) use ($latest) {
                // true kalau row ini sama dengan latest
                return $latest && $row->id === $latest->id ? 1 : 0;
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            PengesahanLhp::create([
                'user_id' => $request->user_id,
                'nama_karyawan' => $request->nama_karyawan,
                'jabatan_karyawan' => $request->jabatan_karyawan,
                'berlaku_mulai' => $request->berlaku_mulai,
                'created_at' => Carbon::now(),
                'created_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Data Pengesahan LHP berhasil disimpan',
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }   
    }

    public function getListKaryawan(Request $request)
    {
        // $search = $request->search;
        $allKaryawan = MasterKaryawan::where('is_active', true)
            ->get();

        $latestPengesahan = PengesahanLhp::orderByDesc('berlaku_mulai')->first();

        return response()->json([
            'data' => $allKaryawan,
            'latestDate' => $latestPengesahan->berlaku_mulai
        ]);
    }



}