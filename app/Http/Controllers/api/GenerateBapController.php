<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\DokumenBap;
use App\Models\LinkLhp;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use Yajra\Datatables\Datatables;

class GenerateBapController extends Controller
{
    public function index()
    {
        $dokumenBap = DokumenBap::with('order');

        return Datatables::of($dokumenBap)->make(true);
    }

    public function getNoQt(Request $request)
    {
        $linkLhps = LinkLhp::where('is_completed', 1)
            ->select('no_quotation', 'periode', 'no_order', 'nama_perusahaan')
            ->orderBy('periode') // pastikan periodenya terurut
            ->get();

        $grouped = [];

        foreach ($linkLhps as $row) {
            $noQuotation = $row->no_quotation;
            if (!isset($grouped[$noQuotation])) {
                $grouped[$noQuotation] = [
                    'no_quotation'     => $noQuotation,
                    'no_order'         => $row->no_order,
                    'nama_perusahaan'  => $row->nama_perusahaan,
                    'periodes'         => [],
                ];
            }
            
            if (!is_null($row->periode)) {
                $grouped[$noQuotation]['periodes'][] = $row->periode;
            }
        }

        foreach ($grouped as &$g) {
            sort($g['periodes']);
        }

        $data = array_values($grouped);


        return response()->json([
            'data' => $data,
            'status' => 200
        ], 200);
    }
}