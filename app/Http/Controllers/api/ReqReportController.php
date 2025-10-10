<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FtcT;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class ReqReportController extends Controller
{
    public function index(Request $request)
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        if ($grade == 'STAFF') {
            $data = FtcT::join('master_karyawan', 't_ftc_t.user_lhp_request', '=', 'master_karyawan.id')
                ->select('master_karyawan.nama_lengkap', 't_ftc_t.id', 'no_sample', 'ftc_lhp_request', 'user_lhp_request')
                ->where('user_lhp_request', $user->id)
                ->orderBy('ftc_lhp_request', 'DESC');

            return Datatables::of($data)->make(true);
        } else {
            $data = FtcT::join('master_karyawan', 't_ftc_t.user_lhp_request', '=', 'master_karyawan.id')
                ->select('master_karyawan.nama_lengkap', 't_ftc_t.id', 'no_sample', 'ftc_lhp_request', 'user_lhp_request')
                ->orderBy('ftc_lhp_request', 'DESC');

            return Datatables::of($data)->make(true);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = FtcT::where('no_sample', $request->no_sample)->first();
            if ($data->ftc_sd != null || $data->ftc_lhp_request != null) {
                return response()->json(['message' => 'Nomor sampel sudah pernah di scan'], 401);
            }
            $data->ftc_lhp_request = Carbon::now()->format('Y-m-d H:i:s');
            $data->user_lhp_request = $this->user_id;
            $data->save();
            return response()->json(['message' => 'Data berhasil disimpan', 'status' => '200'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'No sample tidak ditemukan', 'status' => '400'], 400);
        }
    }

    public function delete(Request $request)
    {
        $data = FtcT::where('no_sample', $request->no_sample)->first();
        $data->ftc_lhp_request = null;
        $data->user_lhp_request = null;
        $data->save();
        return response()->json(['message' => 'Data berhasil dihapus', 'status' => '200'], 200);
    }
}
