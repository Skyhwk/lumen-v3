<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ftc;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class SampleController extends Controller
{
    public function index(Request $request)
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        if($grade == 'STAFF'){
            $data = Ftc::join('master_karyawan','t_ftc.user_sample','=','master_karyawan.id')
                ->select('master_karyawan.nama_lengkap','t_ftc.id','no_sample','ftc_sample','user_sample')
                ->where('user_sample',$user->id)
                ->orderBy('ftc_sample','DESC');

                return Datatables::of($data)->make(true);
        } else {
            $data = Ftc::join('master_karyawan','t_ftc.user_sample','=','master_karyawan.id')
                ->select('master_karyawan.nama_lengkap','t_ftc.id','no_sample','ftc_sample','user_sample')
                ->orderBy('ftc_sample','DESC');

                return Datatables::of($data)->make(true);
        }
    }

    public function store(Request $request){
        try {
            $data = Ftc::where('no_sample', $request->no_sample)->first();
            if($data->ftc_sd != null) {
                return response()->json(['message' => 'Nomor sampel sudah pernah di scan'], 401);
            }
            $data->ftc_sample = Carbon::now()->format('Y-m-d H:i:s');
            $data->user_sample = $this->user_id;
            $data->save();
            return response()->json(['message' => 'Data berhasil disimpan', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => $th->getMessage(), 'status' => '400'], 400);
        }
    }

    public function delete(Request $request){
        $data = Ftc::where('no_sample',$request->no_sample)->first();
        $data->ftc_sample = null;
        $data->user_sample = null;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus', 'status' => '200'], 200);
    }
}
