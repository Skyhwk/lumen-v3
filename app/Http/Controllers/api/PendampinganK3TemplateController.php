<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\PendampinganK3Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;




class PendampinganK3TemplateController extends Controller
{

   public function index(){
      $data = PendampinganK3Template::where('is_active',1);

      return Datatables::of($data)->make(true);
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