<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\File;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Services\BundledTemplateLhps;

use App\Models\{OrderDetail, OrderHeader, PersiapanSampelHeader};

class HasilPengujianController extends Controller
{
    public function index()
    {
        $orders = OrderHeader::select('id', 'no_document', 'tanggal_penawaran', 'no_order', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'alamat_sampling')
            ->where('is_active', true)
            ->latest();

        return DataTables::of($orders)->make(true);
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

                    if ($tglSampling) $steps['sampling'] = ['label' => $labelSampling, 'date' => $tglSampling];

                    $tglAnalisa = optional($track)->ftc_laboratory ?? ($lhps->created_at ?? null);

                    if ($tglAnalisa) $steps['analisa']['date'] = $tglAnalisa;

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

    public function setTemplate($data, $data_detail, $mode_download, $data_custom, $custom2 = null, $mpdf)
    {
        $data->id_kategori_2 = isset($data->id_kategori_2) ? $data->id_kategori_2 : null;
        $data->id_kategori_3 = isset($data->id_kategori_3) ? $data->id_kategori_3 : null;

        $data = $data;

        $dataDetail = is_array($data_detail) ? $data_detail : [];
        $totData = count($dataDetail);

        if ($data->id_kategori_3 == 32) {
            $render = new BundledTemplateLhps;
            $render->DirectESBSolar($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if ($data->id_kategori_3 == 31) {
            $render = new BundledTemplateLhps;
            $render->DirectESBBensin($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if ($data->id_kategori_2 == 34) {
            $render = new BundledTemplateLhps;
            $render->emisisumbertidakbergerak($data, $data_detail, $mode_download, $data_custom, null, $mpdf);
            return true;
        } else if (in_array($data->id_kategori_3, [11, 27])) {
            $parameter = json_decode($data->parameter_uji);

            if (in_array("Sinar UV", $parameter)) {
                $render = new BundledTemplateLhps;
                $render->lhpSinarUV($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            } else if (in_array("Medan Magnit Statis", $parameter) || in_array("Medan Listrik", $parameter) || in_array("Power Density", $parameter)) {
                $render = new BundledTemplateLhps;
                $render->lhpMagnet($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            } else {
                $render = new BundledTemplateLhps;
                $render->lhpLingkungan($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [28])) {
            $render = new BundledTemplateLhps;
            $render->lhpPencahayaan($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if (in_array($data->id_kategori_3, [23, 24, 25])) {
            $parameter = json_decode($data->parameter_uji);
            if (is_array($parameter) && in_array("Kebisingan (P8J)", $parameter)) {
                $render = new BundledTemplateLhps;
                $render->lhpKebisinganPersonal($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            } else {
                $render = new BundledTemplateLhps;
                $render->lhpKebisinganSesaat($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [21])) {
            $parameter = json_decode($data->parameter_uji);
            if (is_array($parameter) && (in_array("ISBB", $parameter) || in_array("ISBB (8 Jam)", $parameter))) {
                $render = new BundledTemplateLhps;
                $render->lhpIklimPanas($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            } else {
                $render = new BundledTemplateLhps;
                $render->lhpIklimDingin($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            }
            return true;
        } else if (in_array($data->id_kategori_3, [13, 14, 15, 16, 18, 19])) {
            $render = new BundledTemplateLhps;
            $render->lhpGetaran($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if (in_array($data->id_kategori_3, [17, 20])) {
            $render = new BundledTemplateLhps;
            $render->lhpGetaranPersonal($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if ($totData <= 20 && stripos($data->sub_kategori, "air") !== false) {
            $render = new BundledTemplateLhps;
            $render->lhpAir20Kolom($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        } else if ($totData > 20 && stripos($data->sub_kategori, "air") !== false) {
            $render = new BundledTemplateLhps;
            $render->lhpAirLebih20Kolom($data, $data_detail, $mode_download, $data_custom, $custom2, $mpdf);
            return true;
        }
    }

    public function generatePdf(Request $request)
    {
        try {
            $orderHeader = OrderHeader::where('no_order', $request->no_order)->first();

            $groupedCFRs = $this->getGroupedCFRs($orderHeader);

            // get released sampel
            $groupedCFRs = collect($groupedCFRs)
                ->filter(fn($cfr) => !empty($cfr['steps']['lhp_release']['date']))
                ->values();

            if ($groupedCFRs->isEmpty()) return response()->json(['message' => 'Dokumen belum siap dirilis'], 400);

            $arrayOfCategories = $groupedCFRs->map(fn($cfr) => $cfr['kategori_3'])->flatten()->unique()->values()->toArray();

            $detail = [];
            foreach ($arrayOfCategories as $category) {
                $filteredCFRs = $groupedCFRs->filter(fn($item) => in_array($category, $item['kategori_3']));
                $titikCount = $groupedCFRs
                    ->filter(fn($item) => in_array($category, $item['kategori_3']))
                    ->flatMap(fn($item) => $item['keterangan_1']) // gabung semua titik
                    ->count();

                $categoryName = explode('-', $category)[1];
                $detail[] = "$categoryName - $titikCount Titik";
            }

            // get bas number
            $no_bas = [];
            $noDocs = PersiapanSampelHeader::select('no_document')->where('no_order', $request->no_order)->pluck('no_document')->toArray();
            foreach ($noDocs as $noDoc) {
                $no_bas[] = str_replace('PS', 'BAS', $noDoc);
            }

            $formattedOrderDate = Carbon::parse($orderHeader->tanggal_order)->translatedFormat('d F Y');
            // $latestReleaseDate = $groupedCFRs->pluck('steps.lhp_release.date')->filter()->max();
            // $formattedReleaseDate = Carbon::parse($latestReleaseDate)->translatedFormat('d F Y');
            $formattedNowDate = Carbon::now()->translatedFormat('d F Y');

            $data = (object) [
                'nama_perusahaan' => $orderHeader->nama_perusahaan,
                'alamat_sampling' => $orderHeader->alamat_sampling,
                'periode' => "$formattedOrderDate - $formattedNowDate",
                'no_order' => $orderHeader->no_order,
                'no_quotation' => $orderHeader->no_document,
                'no_bas' => $no_bas,
                'detail' => $detail
            ];

            $directoryPath = public_path() . '/laporan/hasil_pengujian';
            $filename = 'LHP-' . $request->no_order . '-' . Carbon::now()->format('Ymdhis') . '.pdf';
            $fullPath = $directoryPath . '/' . $filename;

            if (!File::isDirectory($directoryPath)) {
                File::makeDirectory($directoryPath, 0777);
            }

            $mpdf = new Mpdf([
                // 'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'L',
                'margin_top' => 30,
                'margin_bottom' => 30,
                'margin_left' => 30,
                'margin_right' => 30,
                // 'margin_header' => 5,
                // 'margin_footer' => 5
            ]);

            $mpdf->setDisplayMode('fullpage');
            // $mpdf->SetWatermarkImage(public_path() . '/logo-watermark.png');
            // $mpdf->showWatermarkImage = true;
            // $mpdf->watermarkImageAlpha = 0.1;

            $html = view('reports.laporan_hasil_pengujian', compact('data'))->render();

            $mpdf->SetTitle('Laporan Hasil Pengujian');
            $mpdf->SetAuthor('PT Inti Surya Laboratorium');
            $mpdf->SetSubject('Laporan Hasil Pengujian');

            $mpdf->WriteHTML($html);

            $basList = '';
            foreach ($data->no_bas as $no_bas) {
                $basList .= "<li>{$no_bas}</li>";
            }

            $mpdf->SetHTMLFooter('
                <table class="sampling-signature">
                    <tr>
                        <td class="sampling-cell">
                            <div class="section-title">Sampling</div>
                            <table style="margin-top: 15px;">
                                <tr>
                                    <td colspan="3" style="font-size: 8px;">Dokumen Pendukung</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. Order</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;">' . $data->no_order . '</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. Quote</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;">' . $data->no_quotation . '</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 10px;">No. BAS</td>
                                    <td style="font-size: 10px;">:</td>
                                    <td style="font-size: 10px;"></td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="padding-left: 10px;">
                                        <ul>' . $basList . '</ul>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td class="signature-cell" style="font-size: 10px;">
                            Tangerang, ' . $formattedNowDate . '<br /><br /><br /><br /><br /><br />
                            <p class="sign-name">( Abidah Walfathiyyah )</p>
                            <p class="sign-position">Supervisor Technical Control</p>
                        </td>
                    </tr>
                </table>
                <div class="footer-text">
                    Keseluruhan hasil pengujian yang terkandung di dalam Laporan Hasil Pengujian merupakan kerahasiaan dan hak eksklusifitas pelanggan, sesuai dengan penamaan yang tercantum di dalam keseluruhan Laporan Hasil Pengujian ini. PT Inti Surya Laboratorium tidak bertanggung jawab terhadap apapun apabila terjadi penyalahgunaan Laporan Hasil Pengujian termasuk didalamnya, walaupun tidak terbatas, penggandaan dan atau pemalsuan baik data maupun dokumen secara sebagian maupun seluruhnya, yang dimana tanpa sepengetahuan dan ataupun persetujuan secara resmi dari pihak PT Inti Surya Laboratorium.
                </div>
                <div class="footer-company">
                    Ruko Icon Business Park Blok O No.5 - 6 BSD City, Jl. BSD Raya Utama, Cisauk, Sampora Kab. Tangerang 15341<br />
                    T: 021-5088-9889 / contact@intilab.com
                </div>
            ');

            $lhpsTypes = [
                'lhps_air' => ['detail' => 'lhps_air_detail', 'custom' => 'lhps_air_custom'],
                'lhps_emisi' => ['detail' => 'lhps_emisi_detail', 'custom' => null],
                'lhps_emisi_c' => ['detail' => 'lhps_emisi_c_detail', 'custom' => null],
                'lhps_getaran' => ['detail' => 'lhps_getaran_detail', 'custom' => null],
                'lhps_kebisingan' => ['detail' => 'lhps_kebisingan_detail', 'custom' => null],
                'lhps_ling' => ['detail' => 'lhps_ling_detail', 'custom' => null],
                'lhps_medanlm' => ['detail' => 'lhps_medan_l_m_detail', 'custom' => null],
                'lhps_pencahayaan' => ['detail' => 'lhps_pencahayaan_detail', 'custom' => null],
                'lhps_sinaruv' => ['detail' => 'lhps_sinar_u_v_detail', 'custom' => null],
                'lhps_iklim' => ['detail' => 'lhps_iklim_detail', 'custom' => null],
                'lhps_ergonomi' => ['detail' => 'lhps_ergonomi_detail', 'custom' => null],
            ];

            foreach ($groupedCFRs as $cfrItem) {
                $usedLhps = [];
                foreach ($cfrItem['order_details'] as $detail) {
                    foreach ($lhpsTypes as $lhpsKey => $keys) {
                        $lhps = $detail[$lhpsKey] ?? null;

                        if (!$lhps) continue;

                        $noLhp = $lhps['no_lhp'] ?? null;

                        if (in_array($lhpsKey, ['lhps_emisi', 'lhps_emisi_c', 'lhps_medanlm', 'lhps_pencahayaan', 'lhps_iklim']) && $noLhp) {
                            if (in_array($noLhp, $usedLhps)) continue;

                            $usedLhps[] = $noLhp;
                        }

                        $detailData = collect($lhps[$keys['detail']] ?? []);
                        $customData = $lhps[$keys['custom']] ?? null;

                        $groupedByPage = [];
                        if (!empty($customData)) {
                            foreach ($customData as $item) {
                                $page = $item['page'];
                                $groupedByPage[$page][] = $item;
                            }
                        }

                        $mpdf->AddPage('L', 'A4', '', '', '', 10, 10, 23.5, 17, 18, 8);

                        $this->setTemplate((object) $lhps, $detailData, 'downloadLHP', $groupedByPage, null, $mpdf);
                    }
                }
            }

            $mpdf->Output($fullPath, 'F');

            return response()->json([
                'success' => true,
                'message' => 'Berhasil generate Laporan Hasil Pengujian',
                'data' => $filename
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat laporan: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }
}
