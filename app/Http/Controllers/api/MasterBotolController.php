<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class MasterBotolController extends Controller
{

    public function index(){
        $botol = DB::table('master_botol')->get();

        return DataTables::of($botol)->make(true);
    }


    public function getBotolOptions(){
        $botol = DB::table('master_harga_parameter')
        ->select('regen')
        ->distinct()
        ->get();

        return response()->json($botol);
    }


    public function store(Request $request){
        // dd($request->all());
        DB::beginTransaction();
        try {
            DB::table('master_botol')->insert([
                'nama_botol' => $request->nama_botol,
                'volume' => $request->volume,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'created_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil ditambahkan']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal menambahkan data botol', 'error' => $th->getMessage()], 500);
        }
    }

    public function update(Request $request){
        DB::beginTransaction();
        try {
            DB::table('master_botol')->where('id', $request->id)->update([
                'nama_botol' => $request->nama_botol,
                'volume' => $request->volume,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'updated_by' => $this->karyawan
            ]);
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil diupdate']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal mengupdate data botol', 'error' => $th->getMessage()], 500);
        }
    }

    public function destroy(Request $request){
        DB::beginTransaction();
        try {
            DB::table('master_botol')->where('id', $request->id)->delete();
            DB::commit();
            return response()->json(['message' => 'Data botol berhasil dihapus']);
        } catch (\Throwable $th) {
            //throw $th;
            DB::rollBack();
            return response()->json(['message' => 'Gagal menghapus data botol', 'error' => $th->getMessage()], 500);
        }
    }
  
}


