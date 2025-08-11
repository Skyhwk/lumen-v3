<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LogDoor;
use App\Models\Devices;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\User;
use App\Models\UserToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;


class AccessLabLogController extends Controller
{
    public function index(Request $request)
    {
        // dd($request->mode);
        if($request->mode == 'device'){
            $data = Devices::where('id_cabang', $request->cabang)->where('type', 'lab')->where('is_active', true);
        }else{
            $data = MasterKaryawan::where('id_cabang', $request->cabang)->where('is_active', true);
        }
        return Datatables::of($data)->make(true);
    }

    public function detail(Request $request){
        try {
            $data = LogDoor::with('device','karyawan')
            ->whereBetween('tanggal',[$request->tanggal_awal, $request->tanggal_akhir]);
            if($request->mode == 'device'){
                $data->where('kode_mesin',$request->id);
            }else{
                $data->where('userid',$request->id);
            }
            $data->where('userid','!=',null)->orderBy('tanggal','desc');
            // $data->orderBy('tanggal','desc');

            return DataTables::of($data)->make(true);
        }catch (\Exception $th){
            return response()->json([
                'message' => "Error : ". $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile()
            ],500);
        }
    }

    public function getCabang(Request $request){
        $cabang = MasterCabang::where('is_active', true)->get();
        return response()->json($cabang);
    }
}
