<?php

namespace App\Http\Controllers\api;

use App\Models\DataKandidat;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Date;
use App\Models\JadwalLibur;
use Validator;
use Carbon\Carbon;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\DB;


class JadwalLiburController extends Controller
{
 
    public function index(){
        $data = JadwalLibur::where('is_active', true)->get();
        return Datatables::of($data)->make(true);
    }
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = [
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date ? $request->end_date : $request->start_date,
                'created_at' => Carbon::now(),
                'created_by' => $this->karyawan,
            ];   
            JadwalLibur::create($data);
            DB::commit();   
            return response()->json(['message' => 'Jadwal libur created successfully.'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => "failed",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    
    public function destroy(Request $request)
    {
        DB::beginTransaction();
        try {
   
            $jadwalLibur = JadwalLibur::where("id",$request->id);
            $jadwalLibur->update([
                'is_active' => false,
                'update_at' => Carbon::now(),
                'update_by' => $this->karyawan,
            ]);
 
            DB::commit();
            return response()->json(['message' => 'Jadwal libur updated successfully.'], 200);
    
        } catch (\Exception $e) {
            // Respon error
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred while updating jadwal libur.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    public function update(Request $request)
 {
        $data = JadwalLibur::where('id', $request->id)->first();
        DB::beginTransaction();
        try {
            $data->update([
                'judul' => $request->judul,
                'deskripsi' => $request->deskripsi,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'update_at' => Carbon::now(),
                'update_by' => $this->karyawan,
            ]);
            DB::commit();
            return response()->json(['message' => 'Jadwal libur updated successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'An error occurred while updating jadwal libur.',
                'details' => $e->getMessage(),
            ], 500);
        }
}
}