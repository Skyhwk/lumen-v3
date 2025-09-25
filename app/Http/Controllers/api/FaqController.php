<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;
use Carbon\Carbon;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $faq = Faq::where('is_active', 1)->get();
        return DataTables::of($faq)->make(true);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->id == null || $request->id == '') {
                Faq::create([
                    'pertanyaan' => $request->pertanyaan,
                    'jawaban' => $request->jawaban,
                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'created_by' => $this->karyawan
                ]);
            } else {
                Faq::where('id', $request->id)->update([
                    'pertanyaan' => $request->pertanyaan,
                    'jawaban' => $request->jawaban,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'updated_by' => $this->karyawan
                ]);
            }
            DB::commit();
            return response()->json([
                'message' => 'Faq berhasil disimpan.',
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            Faq::where('id', $request->id)->update([
                'is_active' => 0,
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Faq berhasil dihapus.',
                'status' => '200'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    // public function getRegulasi(Request $request)
    // {
    //     $data = MasterRegulasi::with('bakumutu:id_regulasi,parameter,method')
    //         ->whereHas('bakumutu')
    //         ->select('id', 'nama_kategori', 'peraturan')
    //         ->where('is_active', true)
    //         ->get()
    //         ->groupBy('nama_kategori');

    //     return response()->json($data, 200);
    // }
}
