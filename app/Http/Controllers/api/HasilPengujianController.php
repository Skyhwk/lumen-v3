<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\File;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;
use App\Services\MpdfService as Mpdf;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Services\BundledTemplateLhps;

use App\Models\{OrderDetail, OrderHeader, PersiapanSampelHeader};
use App\Services\GroupedCfrByLhp;
use Illuminate\Support\Facades\DB;

class HasilPengujianController extends Controller
{
    public function index(Request $request)
    {
        $subQuery = DB::table('order_detail')
            ->select(
                'id_order_header',
                'periode',
                DB::raw('GROUP_CONCAT(tanggal_sampling SEPARATOR ",") as tanggal_sampling'),
                DB::raw('GROUP_CONCAT(tanggal_terima SEPARATOR ",") as tanggal_terima'),
                DB::raw('GROUP_CONCAT(cfr SEPARATOR ",") as cfr')
            )
            ->groupBy('id_order_header', 'periode');

        $query = DB::table('order_header as oh')
            ->joinSub($subQuery, 'od', function ($join) {
                $join->on('oh.id', '=', 'od.id_order_header');
            })
            ->where('oh.is_active', true)
            ->select(
                'oh.id',
                'oh.no_document',
                'oh.tanggal_penawaran',
                'oh.no_order',
                'oh.tanggal_order',
                'oh.nama_perusahaan',
                'oh.konsultan',
                'oh.alamat_sampling',
                'od.periode',
                'od.tanggal_sampling',
                'od.tanggal_terima',
                'od.cfr'
            )
            ->orderByDesc('oh.created_at');

        return DataTables::of($query)
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('oh.no_document', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('oh.no_order', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('periode', function ($query, $keyword) {
                $query->where('od.periode', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where('oh.nama_perusahaan', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('konsultan', function ($query, $keyword) {
                $query->where('oh.konsultan', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                $query->where('od.tanggal_sampling', 'LIKE', '%' . $keyword . '%');
            })
            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('od.tanggal_terima', 'LIKE', '%' . $keyword . '%');
            })
            ->editColumn('cfr', fn($row) => $row->cfr ? array_values(array_unique(explode(',', $row->cfr))) : null)
            ->editColumn('tanggal_sampling', fn($row) => $row->tanggal_sampling ? array_values(array_unique(explode(',', $row->tanggal_sampling))) : null)
            ->editColumn('tanggal_terima', fn($row) => $row->tanggal_terima ? array_values(array_unique(explode(',', $row->tanggal_terima))) : null)
            ->make(true);
    }

    // Helper methods
    private function convertBulan($keyword, $bulanMap)
    {
        $keywordLower = strtolower($keyword);
        foreach ($bulanMap as $namaBulan => $angkaBulan) {
            if (strpos($keywordLower, $namaBulan) !== false) {
                return str_replace($namaBulan, $angkaBulan, $keywordLower);
            }
        }
        return $keywordLower;
    }

    private function filterTanggal($query, $column, $keyword, $bulanMap)
    {
        $query->where(function ($q) use ($column, $keyword, $bulanMap) {
            if (is_numeric($keyword)) {
                // Untuk angka, search langsung di kolom tanggal
                $q->whereRaw("$column LIKE ?", ['%' . $keyword . '%']);
            } else {
                $converted = $this->convertBulan($keyword, $bulanMap);
                // Gunakan indexed column jika memungkinkan
                $q->whereRaw("DATE_FORMAT($column, '%d-%m-%Y') LIKE ?", ['%' . $converted . '%']);
            }
        });
    }

    public function detail(Request $request)
    {
        $orderHeader = OrderHeader::find($request->id_order_header);

        $groupedData = (new GroupedCfrByLhp($orderHeader, $request->periode))->get();

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
