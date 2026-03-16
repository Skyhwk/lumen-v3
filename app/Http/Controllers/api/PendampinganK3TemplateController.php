<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\MenuFdl;
use App\Models\PendampinganK3Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\VarDumper\Cloner\Data;
use Yajra\Datatables\Datatables;




class PendampinganK3TemplateController extends Controller
{

   public function index(){
      $data = PendampinganK3Template::where('is_active',1);

      return Datatables::of($data)->make(true);
   }

   public function listAkses(){
      $data = MenuFdl::where('title', 'Pendampingan K3')->whereNotNull('access_restricted')->first();
      $users = MasterKaryawan::select(['id', 'nama_lengkap', 'jabatan'])->whereIn('id', json_decode($data->access_restricted))->get();

      return Datatables::of($users)->make(true);
   }

   public function deleteAkses(Request $request){
      $data = MenuFdl::where('title', 'Pendampingan K3')->whereNotNull('access_restricted')->first();
      $access = $data->access_restricted;
      $access = json_decode($access);
      $access = array_diff($access, [$request->id]);
      $data->access_restricted = json_encode($access);
      $data->save();
      return response()->json([
         'message' => 'Data berhasil dihapus',
      ], 200);
   }

   public function getKaryawanBelumAkses(Request $request){
      $data = MenuFdl::where('title', 'Pendampingan K3')->whereNotNull('access_restricted')->first();
      $karyawan = MasterKaryawan::select(['id', 'nama_lengkap', 'jabatan'])->whereNotIn('id', json_decode($data->access_restricted))->where('is_active', 1)->get();
      return response()->json([
         'message' => 'Berhasil mendapatkan data',
         'data' => $karyawan
      ], 200);
   }

   public function storeAkses(Request $request){
      $data = MenuFdl::where('title', 'Pendampingan K3')->whereNotNull('access_restricted')->first();
      $access = $data->access_restricted;
      $access = json_decode($access);
      $access = array_merge($access, [$request->karyawan_id]);
      $data->access_restricted = json_encode($access);
      $data->save();

      return response()->json([
         'message' => 'Data berhasil ditambahkan',
      ], 200);
   }

   public function store(Request $request){
      $data = PendampinganK3Template::create($request->all());
      return response()->json([
         'message' => 'Data berhasil disimpan',
      ], 201);
   }

   public function update(Request $request){
      $data = PendampinganK3Template::findOrFail($request->id);
      $data->update($request->all());
      return response()->json([
         'message' => 'Data berhasil diupdate',
      ], 200);
   }

   public function delete(Request $request){
      $data = PendampinganK3Template::findOrFail($request->id);
      $data->is_active = false; 
      $data->save();
      return response()->json([
         'message' => 'Data berhasil dihapus',
      ], 200);
   }
}