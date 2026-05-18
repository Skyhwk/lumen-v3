<?php
namespace App\Http\Controllers\mobile;

use App\Models\SarHeader;
use App\Models\SarDetail;
use App\Models\ProsesFdlSar;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Services\GenerateQrDocumentLhp;
use App\Services\RenderLhpSar;

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

    public function index (Request $request) {
        $proses = ProsesFdlSar::where('is_completed', true)->where('karyawan_id', 127)->get();
        $data = SarHeader::whereIn('no_order', $proses->pluck('no_order'))->where('is_active', true)->where('is_completed', true)->get();

        return response()->json([
            'message' => 'success',
            'data' => $data
            ], 200);
    }

    public function renderPdf(Request $request)
    {
        $hasilUjiSAR = SarHeader::with('detail')->findOrFail($request->id);

        $hasilUjiSAR->tanggal_lhp = date('Y-m-d');

        $file_qr = new GenerateQrDocumentLhp();
        if ($path = $file_qr->insertSAR('LHP_SAR', $hasilUjiSAR, $this->karyawan)) {
            $hasilUjiSAR->file_qr = $path;
        }

        $filename = RenderLhpSar::setDataHeader($hasilUjiSAR)->setDataDetail($hasilUjiSAR->detail)->render();

        $hasilUjiSAR->file_lhp = $filename;
        $hasilUjiSAR->save();

        return response()->json($filename, 200);
    }
}