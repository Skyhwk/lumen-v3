<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use DataTables;

use App\Services\RenderLhpSar;
use App\Services\GenerateQrDocumentLhp;

use App\Models\{SarHeader};

class HasilUjiSARController extends Controller
{
    public function index()
    {
        $hasilUjiSARs = SarHeader::latest();

        return DataTables::of($hasilUjiSARs)->make(true);
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
