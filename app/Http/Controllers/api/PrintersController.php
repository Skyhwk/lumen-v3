<?php

namespace App\Http\Controllers\api;

use App\Models\Printers;
use App\Models\MasterDivisi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class PrintersController extends Controller
{
    	public function index(Request $request){
			$data = Printers::with('divisi');

            return Datatables::of($data)->make(true);
        }

		public function store(Request $request)
		{
			try {
				if ($request->id) {
					$printer = Printers::where('id', $request->id)->first();
		
					if (!$printer) {
						return response()->json(['error' => 'Data tidak ditemukan'], 404);
					}
		
					$printer->update([
						'share_name' => $request->share_name,
						'is_network' => $request->is_network == "true" ? true : false,
						'is_default' => $request->is_default == "true" ? true : false,
						'server' => $request->server_path,
						'name' => $request->name,
						'id_divisi' => $request->id_divisi,
						'full_path' => $request->full_path,
					]);
		
					return response()->json([
						"data" => $printer,
						"status" => 200,
						"message" => "Update data berhasil"
					], 200);
		
				} else {
				
					$printer = new Printers;
					$printer->share_name = $request->share_name;
					$printer->is_network = $request->is_network == "true" ? true : false;
					$printer->is_default = $request->is_default == "true" ? true : false;
					$printer->server = $request->server_path;
					$printer->name = $request->name;
					$printer->id_divisi = $request->id_divisi;
					$printer->full_path = $request->full_path;
					$printer->save();
					return response()->json([
					
						"status" => 201,
						"message" => "Data berhasil disimpan"
					], 201);
				}
			} catch (\Exception $e) {
				dd($e);
				return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
			}
		}
		
		public function getDivisi()
		{
			$data = MasterDivisi::where('is_active', 1)->get();
			return response()->json([
				"data" => $data,
				"status" => 200,
				"message" => "Data berhasil disimpan"
			], 200);
		}
		
		public function destroy(Request $request)
		{
			$printer = Printers::where('id', $request->id)->delete();
			
			return response()->json([
				"status" => 200,
				"message" => "Data berhasil dihapus"
			], 200);
		}
	
}