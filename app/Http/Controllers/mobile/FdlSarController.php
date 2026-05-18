<?php
namespace App\Http\Controllers\mobile;

use App\Models\SarHeader;
use App\Models\SarDetail;
use App\Models\ProsesFdlSar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FdlSarController extends Controller
{
    public function checkQr(Request $request)
    {
        $data = SarHeader::where('no_order', $request->no_order)->where('is_active', true)->first();
        if(!$data) {
            return response()->json([
                'message' => 'Qr Tidak Ditemukan',
                'data' => null
                ], 401);
        }
        return response()->json([
            'message' => 'Data Ditemukan',
            'data' => $data
            ], 200);
    }

    public function checkUsable(Request $request)
    {
        $data = ProsesFdlSar::where('karyawan_id', $request->user_id)->where('is_completed', false)->first();
        $header = [];
        $isUsable = false;
        if ($data) {
            $header = SarHeader::where('no_order', $data->no_order)->where('is_active', true)->first();
            $isUsable = true;
        }
        return response()->json([
            'message' => 'success',
            'is_usable' => $isUsable,
            'data' => $header
            ], 200);
    }
}