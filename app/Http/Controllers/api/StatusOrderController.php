<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Datatables;
use Exception;

class StatusOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices'
                ])
                    ->where('id_cabang', $request->cabang)
                    ->whereHas('orderHeader')
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'orderHeader.invoices'
                ])
                    ->where('id_cabang', $request->cabang)
                    ->whereHas('orderHeader')
                    // ->where('flag_status', '!=', 'ordered')
                    // ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            if ($jabatan == 24 || $jabatan == 86) { // sales staff || Secretary Staff
                $data->where('sales_id', $this->user_id);
            } else if ($jabatan == 21 || $jabatan == 15 || $jabatan == 154) { // sales supervisor || sales manager || senior sales manager
                $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)->pluck('id')->toArray();
                array_push($bawahan, $this->user_id);
                $data->whereIn('sales_id', $bawahan);
            }

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->addColumn('total_invoice', function ($row) {
                    if (!$row->orderHeader) return 0;
                    return $row->orderHeader->invoices->count();
                })
                ->addColumn('nilai_pelunasan', function ($row) {
                    if (!$row->orderHeader || $row->orderHeader->invoices->isEmpty()) return '-';
                    $totalPelunasan = $row->orderHeader->invoices->sum('nilai_pelunasan');

                    return $totalPelunasan;
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
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

    private function getGroupedCFRs($orderHeader)
    {
        try {
            $orderDetails = OrderDetail::select('id', 'id_order_header', 'cfr', 'periode', 'no_sampel', 'keterangan_1', 'tanggal_terima', 'status', 'kategori_2', 'kategori_3')
                ->with([
                    'TrackingSatu:id,no_sample,ftc_sd,ftc_verifier,ftc_laboratory',
                    'lhps_air',
                    'lhps_emisi',
                    'lhps_emisi_c',
                    'lhps_getaran',
                    'lhps_kebisingan',
                    'lhps_ling',
                    'lhps_medanlm',
                    'lhps_pencahayaan',
                    'lhps_sinaruv',
                    'lhps_iklim',
                    'lhps_ergonomi',
                ])
                ->where([
                    'id_order_header' => $orderHeader->id,
                    'is_active' => true
                ])->get();

            $groupedData = $orderDetails->groupBy(['cfr', 'periode'])->map(fn($periodGroups) =>
            $periodGroups->map(function ($itemGroup) use ($orderHeader) {
                $mappedDetails = $itemGroup->map(function ($item) use ($orderHeader) {
                    $steps = $this->initializeSteps($orderHeader->tanggal_order);

                    $track = $item->TrackingSatu;

                    $lhps = collect([
                        $item->lhps_air,
                        $item->lhps_emisi,
                        $item->lhps_emisi_c,
                        $item->lhps_getaran,
                        $item->lhps_kebisingan,
                        $item->lhps_ling,
                        $item->lhps_medanlm,
                        $item->lhps_pencahayaan,
                        $item->lhps_sinaruv,
                        $item->lhps_iklim,
                        $item->lhps_ergonomi,
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
                    $kategori_validation = ['13-Getaran', "14-Getaran (Bangunan)", '15-Getaran (Kejut Bangunan)', '16-Getaran (Kenyamanan & Kesehatan)', "17-Getaran (Lengan & Tangan)", "18-Getaran (Lingkungan)", "19-Getaran (Mesin)",  "20-Getaran (Seluruh Tubuh)", "21-Iklim Kerja", "23-Kebisingan", "24-Kebisingan (24 Jam)", "25-Kebisingan (Indoor)", "28-Pencahayaan"];
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
            dd($th);
        }
    }

    public function detail(Request $request)
    {
        $orderHeader = OrderHeader::find($request->id_order_header);

        $groupedData = $this->getGroupedCFRs($orderHeader);

        return response()->json(['groupedCFRs' => $groupedData], 200);
    }
}
