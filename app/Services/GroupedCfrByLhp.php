<?php

namespace App\Services;

use App\Models\OrderDetail;
use Illuminate\Support\Carbon;

class GroupedCfrByLhp
{
    protected $orderHeader;
    protected $periode;

    public function __construct($orderHeader, $periode = null)
    {
        $this->orderHeader = $orderHeader;
        $this->periode = $periode;
    }

    public function get()
    {
        $data = $this->getGroupedCFRs($this->orderHeader, $this->periode);
        return $data;
    }

    private function getGroupedCFRs($orderHeader, $periode)
    {
        try {
            $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'kategori_1', 'keterangan_1', 'tanggal_sampling', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3', 'parameter', 'regulasi')
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
                    "lhps_padatan",
                    "lhp_psikologi",

                    "wsValueAir",
                    "wsValueUdara",
                    "wsValueEmisiCerobong",
                ])
                ->withAnyDataLapangan()
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
                    $tanggal_order = Carbon::parse($orderHeader->created_at)->format('Y-m-d');
                    $steps = $this->initializeSteps($tanggal_order);

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
                        $item->lhp_psikologi,
                    ])->first(fn($lhps) => $lhps !== null);

                    // $tglSampling = optional($track)->ftc_verifier
                    //     ?? optional($track)->ftc_sd
                    //     ?? ($lhps->created_at ?? null)
                    //     ?? $item->tanggal_terima;
                    
                    $tglSampling = $item->kategori_1 != 'SD'
                        ? ($item->kategori_3 !== '118-Psikologi' ? ($item->tanggal_sampling ?? null) : ($item->tanggal_terima ?? null))
                        : ($item->tanggal_terima ?? null);

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

                    $labelSampling = $item->kategori_3 == '118-Psikologi' ? 'Direct' : $labelSampling;

                    if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

                    $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);

                    $isTglAnalisaEqualTglSampling = in_array($item->kategori_3, $kategori_validation) || str_contains($item->parameter, 'Ergonomi') || str_contains($item->kategori_3, 'Psikologi');
                    if ($isTglAnalisaEqualTglSampling) {
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

                $tanggal_order = Carbon::parse($orderHeader->created_at)->format('Y-m-d');
                $stepsByCFR = $this->initializeSteps($tanggal_order);
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
}