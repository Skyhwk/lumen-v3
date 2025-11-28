<?php

namespace App\Http\Controllers\external;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

//model
use App\Models\HoldHp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\Invoice;

class LHPHandleController extends BaseController
{
    public function cekLHP(Request $request)
    {
        $token = str_replace(' ', '+', $request->token);
        // $token = $request->token;
        
        if($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();
            if($cekData){
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();

                if($cekData){
                    $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                    $checkHold =HoldHp::where('no_order',$dataLhp->no_order)->first();
                    if ($checkHold && $checkHold->is_hold == 1) {
                        // Sudah di-hold, jangan tampilkan
                        return response()->json(['message' => 'Document On Hold'], 405);
                    }else{
                        if($dataLhp && isset($dataLhp->filename) && $dataLhp->filename != null && $dataLhp->filename != '') {
                            if(file_exists(public_path('laporan/hasil_pengujian/' . $dataLhp->filename))) {
                                return response()
                                ->json(
                                    [
                                        'data' => $dataLhp,
                                        'message' => 'data hasbenn show',
                                        'qt_status' => $cekData->quotation_status,
                                        'status' => '201',
                                        'uri' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename
                                    ], 200);
                                return response()->json(['message' => 'Document found', 'data' => env('APP_URL') . '/public/laporan/hasil_pengujian/' . $dataLhp->filename], 200);
                            } else {
                                return response()->json(['message' => 'Document found but file not exists'], 403);
                            }
                            // return response()->json(['message' => 'Document found', 'data' => $dataLhp->filename], 200);
                        } else if ($dataLhp && $dataLhp->filename == null || $dataLhp->filename == ''){
                            return response()->json(['message' => 'Document found but file not exists'], 403);
                        } else {
                            return response()->json(['message' => 'Document not found'], 404);
                        }
                    }
                } else {
                    return response()->json(['message' => 'Token not found'], 401);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }

    public function newCheckLhp(Request $request)
    {
        $token = str_replace(' ', '+', $request->token);
        if($token == null || $token == '') {
            return response()->json(['message' => 'Token tidak boleh kosong'], 430);
        } else {
            $cekData = DB::table('generate_link_quotation')->where('token', $token)->first();
            
            if($cekData){
                $dataLhp = DB::table('link_lhp')->where('id_token', $cekData->id)->first();
                $periode = $dataLhp->periode;
                $noOrder = $dataLhp->no_order;

                $fileName = $dataLhp->filename ?? null;

                $dataOrder = OrderHeader::where('no_order', $noOrder)->where('is_active', true)->first();
                $cekInvoice = Invoice::where('no_order', $noOrder)->where('periode', $periode)->where('is_active', true)->get() ?? null;

                if($dataOrder){
                    $dataGrouped = $this->getGroupedCFRs($dataOrder, $periode);
                    return response()->json(['message' => 'Data LHP found', 'data' => $dataGrouped, 'order' => $dataOrder, 'periode' => $periode, 'invoice' => $cekInvoice, 'fileName' => $fileName], 200);
                } else {
                    return response()->json(['message' => 'Data Order not found'], 404);
                }
            } else {
                return response()->json(['message' => 'Token not found'], 401);
            }
        }
    }

    private function initializeSteps($orderDate)
    {
        return [
            'order' => ['label' => 'Order', 'date' => $orderDate],
            'sampling' => ['label' => 'Sampling', 'date' => null],
            'analisa' => ['label' => 'Analisa', 'date' => null],
            'drafting' => ['label' => 'Drafting', 'date' => null],
            'lhp_release' => ['label' => 'LHP Release', 'date' => null],
        ];
    }

    private function detectActiveStep($steps)
    {
        $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
            ->search(fn($step) => empty($steps[$step]['date']));

        return $search === false ? 5 : $search;
    }

    private function detectActiveStepByGroup($details)
    {
        $search = collect(['order', 'sampling', 'analisa', 'drafting', 'lhp_release'])
            ->search(fn($step) => $details->contains(fn($d) => empty($d->steps[$step]['date'])));

        return $search === false ? 5 : $search;
    }

    private function getGroupedCFRs($orderHeader, $periode)
    {
        try {
            $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'keterangan_1', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3')
                ->with([
                    'TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory',
                    "lhps_air",
                    "lhps_emisi",
                    "lhps_emisi_c",
                    "lhps_emisi_isokinetik",
                    "lhps_getaran",
                    "lhps_kebisingan",
                    "lhps_kebisingan_personal",
                    "lhps_ling",
                    "lhps_medanlm",
                    "lhps_pencahayaan",
                    "lhps_sinaruv",
                    "lhps_ergonomi",
                    "lhps_iklim",
                    "lhps_swab_udara",
                    "lhps_microbiologi",
                    "lhps_padatan"
                ])
                ->where([
                    'id_order_header' => $orderHeader->id,
                    'is_active' => true,
                ])
                ->when(!empty($periode), function ($query) use ($periode) {
                    $query->where('periode', $periode);
                })->get();

            $groupedData = $orderDetails->groupBy(['cfr', 'periode'])->map(fn($periodGroups) =>
            $periodGroups->map(function ($itemGroup) use ($orderHeader) {
                $mappedDetails = $itemGroup->map(function ($item) use ($orderHeader) {
                    $steps = $this->initializeSteps($orderHeader->tanggal_order);

                    $track = $item->TrackingSatu;

                    $lhps = collect([
                        $item->lhps_air,
                        $item->lhps_emisi,
                        $item->lhps_emisi_c,
                        $item->lhps_emisi_isokinetik,
                        $item->lhps_getaran,
                        $item->lhps_kebisingan,
                        $item->lhps_kebisingan_personal,
                        $item->lhps_ling,
                        $item->lhps_medanlm,
                        $item->lhps_pencahayaan,
                        $item->lhps_sinaruv,
                        $item->lhps_ergonomi,
                        $item->lhps_iklim,
                        $item->lhps_swab_udara,
                        $item->lhps_microbiologi,
                        $item->lhps_padatan,
                    ])->first(fn($lhps) => $lhps !== null);

                    $tglSampling = optional($track)->ftc_verifier
                        ?? optional($track)->ftc_sd
                        ?? ($lhps->created_at ?? null)
                        ?? $item->tanggal_terima;

                    $labelSampling = optional($track)->ftc_verifier
                        ? 'Sampling'
                        : (optional($track)->ftc_sd
                            ? 'Sampel Diterima'
                            : (($lhps->created_at ?? null)
                                ? 'Direct'
                                : ($item->tanggal_terima ? 'Sampling' : null)));

                    $kategori_validation = 
                    [
                        '13-Getaran', 
                        "14-Getaran (Bangunan)", 
                        '15-Getaran (Kejut Bangunan)', 
                        '16-Getaran (Kenyamanan & Kesehatan)', 
                        "17-Getaran (Lengan & Tangan)", 
                        "18-Getaran (Lingkungan)", 
                        "19-Getaran (Mesin)",  
                        "20-Getaran (Seluruh Tubuh)", 
                        "21-Iklim Kerja", 
                        "23-Kebisingan", 
                        "24-Kebisingan (24 Jam)",
                        "25-Kebisingan (Indoor)", 
                        "28-Pencahayaan"
                    ];

                    if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

                    $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);

                    if (in_array($item->kategori_3, $kategori_validation)) {
                        $steps['analisa']['date'] = $tglSampling;
                    } else {
                        if ($tglAnalisa) $steps['analisa']['date'] = $tglAnalisa;
                    }

                    $steps['drafting']['date'] = $lhps->created_at ?? null;

                    $steps['lhp_release']['date'] = $lhps->approved_at ?? null;

                    $steps['activeStep'] = $this->detectActiveStep($steps);

                    $item->steps = $steps;

                    return $item;
                });

                $stepsByCFR = $this->initializeSteps($orderHeader->tanggal_order);
                foreach (['sampling', 'analisa', 'drafting', 'lhp_release'] as $step) {
                    // Cek SEMUA detail sudah punya tanggal untuk step ini
                    $allCompleted = $mappedDetails->every(function ($detail) use ($step) {
                        return !empty($detail->steps[$step]['date']);
                    });

                    if ($allCompleted) {
                        // ...isi tanggal parent-nya, ambil yang paling awal.
                        $earliestDate = $mappedDetails->pluck("steps.{$step}.date")->filter()->min();
                        $label = $mappedDetails->first()->steps[$step]['label']; // Ambil label dari item pertama
                        $stepsByCFR[$step] = ['label' => $label, 'date' => $earliestDate];
                    }
                }

                $stepsByCFR['activeStep'] = $this->detectActiveStepByGroup($mappedDetails);

                return [
                    'cfr' => $itemGroup->first()->cfr,
                    'periode' => $itemGroup->first()->periode,
                    'keterangan_1' => $itemGroup->pluck('keterangan_1')->toArray(),
                    'kategori_3' => $itemGroup->pluck('kategori_3')->toArray(),
                    'no_sampel' => $itemGroup->pluck('no_sampel')->toArray(),
                    'total_no_sampel' => $itemGroup->count(),
                    'order_details' => $mappedDetails->toArray(),
                    'steps' => $stepsByCFR
                ];
            }))->flatten(1)->values();

            return $groupedData;
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error', 'error' => $th->getMessage()], 500);
        }
    }
}
