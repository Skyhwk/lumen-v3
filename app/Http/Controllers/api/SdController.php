<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ftc;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class SdController extends Controller
{
    public function index(Request $request)
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        if($grade == 'STAFF'){
            $data = Ftc::join('master_karyawan','t_ftc.user_sd','=','master_karyawan.id')
                ->select('master_karyawan.nama_lengkap','t_ftc.id','no_sample','ftc_sd','user_sd')
                ->where('user_sd',$user->id)
                ->orderBy('ftc_sd','DESC');

                return Datatables::of($data)->make(true);
        } else {
            $data = Ftc::join('master_karyawan','t_ftc.user_sd','=','master_karyawan.id')
                ->select('master_karyawan.nama_lengkap','t_ftc.id','no_sample','ftc_sd','user_sd')
                ->orderBy('ftc_sd','DESC');

                return Datatables::of($data)->make(true);
        }
    }

    public function store(Request $request){
        try {
            $data = Ftc::where('no_sample', $request->no_sample)->first();
            if($data->ftc_sd != null) {
                return response()->json(['message' => 'Nomor sampel sudah pernah di scan'], 401);
            }
            $data->ftc_sd = Carbon::now()->format('Y-m-d H:i:s');
            $data->user_sd = $this->user_id;
            $data->save();

            $orderD = OrderDetail::where('no_sampel', $request->no_sample)->first();
            $orderD->tanggal_terima = Carbon::now()->format('Y-m-d');
            $orderD->save();
            
            return response()->json(['message' => 'Data berhasil disimpan', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage(), 'status' => '400'], 400);
        }
    }

    public function delete(Request $request){
        $data = Ftc::where('no_sample',$request->no_sample)->first();
        $data->ftc_sd = null;
        $data->user_sd = null;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus', 'status' => '200'], 200);
    }
}
