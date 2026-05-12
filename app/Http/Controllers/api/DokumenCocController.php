<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Services\GenerateDokumenCocService;

use App\Models\DokumenCoc;

class DokumenCocController extends Controller
{
    public function generateDokumenCoc(Request $request)
    {
        $no_lhp = $request->no_lhp;

        $dokumenCoc = DokumenCoc::where('no_lhp', $no_lhp)->first();
        if ($dokumenCoc) {
            return response()->json([
                'message'  => 'Berhasil mendapatkan data COC',
                'filename' => $dokumenCoc->filename,
            ], 200);
        }

        $service = new GenerateDokumenCocService($no_lhp);
        $filename = $service->generate();

        return response()->json([
            'message'  => 'Berhasil generate data COC',
            'filename' => $filename,
        ], 200);
    }
}
