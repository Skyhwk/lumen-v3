<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\QrDocument;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\JobTask;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as Mpdf;
use App\Services\TranslatorService as Translator;
use Carbon\Carbon;

class RenderKontrakCopy
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function renderDataQuotation($id, $lang = 'id')
    {
        DB::beginTransaction();
        try {
            app()->setLocale($lang);
            Carbon::setLocale($lang);
            $update = QuotationKontrakH::where('id', $id)->first();
            $filename = self::renderHeader($id, $lang);
            if ($update && $filename) {
                $update->filename = $filename;
                $update->save();

                // JobTask::insert([
                //     'job' => 'RenderPdfPenawaran',
                //     'status' => 'success',
                //     'no_document' => $update->no_document,
                //     'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                // ]);
            }
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            // JobTask::insert([
            //     'job' => 'RenderPdfPenawaran',
            //     'status' => 'failed',
            //     'no_document' => $update->no_document,
            //     'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            // ]);
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ], 401);
        }
    }

    public function renderHeader($id, $lang)
    {
        try {
            $data = QuotationKontrakH::with('cabang', 'sales')
                ->where('is_active', true)
                ->where('id', $id)
                ->first();

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_header' => 3, // 30mm not pixel
                'margin_bottom' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            if ($lang == 'zh') {
                $customFontPath = resource_path('fonts/Noto_Serif_SC');
                $mpdfConfig['fontDir'] = array_merge($mpdfConfig['fontDir'] ?? [], [
                    $customFontPath,
                ]);

                $mpdfConfig['fontdata'] = [
                    'notoserifsc' => [
                        'R' => 'NotoSerifSC-Regular.ttf',
                    ],
                ];
                $mpdfConfig['default_font'] = 'notoserifsc';
            }

            $pdf = new Mpdf($mpdfConfig);
            // if(isset($request->protect) && $request->protect != null)$pdf->SetProtection(array(), $request->protect, $request->protect);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            // $pdf->SetWatermarkText('CONFIDENTIAL');
            // $pdf->showWatermarkText = true;
            $qr_img = '';
            $qr = DB::table('qr_documents')
                ->where(['id_document' => $id, 'type_document' => 'quotation_kontrak'])
                ->whereJsonContains('data->no_document', $data->no_document)
                ->first();

            if ($qr) {
                $qr_data = json_decode($qr->data, true);
                if (isset($qr_data['no_document']) && $qr_data['no_document'] == $data->no_document) {
                    $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                }
            }

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => __('QTC.footer.center_content', ['page' => '{PAGENO}', 'total_pages' => '{nbpg}']),
                        // 'content' => 'Hal {PAGENO} dari {nbpg}',
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => __('QTC.footer.right_content') . ' <br> {DATE YmdGi}',
                        'font-size' => 5,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'L' => array(
                        'content' => '' . $qr_img . '',
                        'font-size' => 4,
                        'font-style' => 'I',
                        // 'font-style' => 'B',
                        'font-family' => 'serif',
                        'color' => '#000000'
                    ),
                    'line' => -1,
                )
            );

            $pdf->setFooter($footer);
            $konsultant = '';
            $jab_pic_or = '';
            $jab_pic_samp = '';
            if ($data->konsultan != '') {
                $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
                $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
            } else {
                $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
            }

            if ($data->jabatan_pic_order != '')
                $jab_pic_or = ' (' . $data->jabatan_pic_order . ')';
            if ($data->jabatan_pic_sampling != '')
                $jab_pic_samp = ' (' . $data->jabatan_pic_sampling . ')';

            $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';

            $detail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $id)
                ->orderBy('periode_kontrak', 'asc')
                ->get();

            $period = [];
            foreach ($detail as $key => $val) {
                if ($key == 0) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        if ($v_->periode_kontrak === '')
                            continue;
                        array_push($period, self::tanggal_indonesia($v_->periode_kontrak, 'period'));
                        continue;
                    }
                } else if ($key == (count($detail) - 1)) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        array_push($period, self::tanggal_indonesia($v_->periode_kontrak, 'period'));
                        continue;
                    }
                }
            }

            if (explode(" ", $period[0])[1] == explode(" ", $period[(count($period) - 1)])[1]) {
                $period = explode(" ", $period[0])[0] . ' - ' . $period[(count($period) - 1)];
            } else {
                $period = $period[0] . ' - ' . $period[(count($period) - 1)];
            }


            $pdf->SetHTMLHeader(' <table class="tabel" width="100%">
                <tr class="tr_top">
                    <td class="text-left text-wrap" style="width: 33.33%;">
                        <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                    </td>
                    <td style="width: 33.33%; text-align: center;">
                        <h5 style="text-align:center; font-size:14px;">
                            <b>
                                <u>' . strtoupper(__('QTC.header.quotation')) . '</u>
                            </b>
                        </h5>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                        <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $period . ' </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="font-size: 9px; text-align:right;">
                            <b>PT INTI SURYA LABORATORIUM</b>
                            <br>
                                <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->cabang->alamat_cabang . '</span>
                            <br>
                                <span>T : ' . $data->cabang->tlp_cabang . ' - sales@intilab.com</span>
                            <br>www.intilab.com
                        </p>
                    </td>
                </tr>
            </table>
            <table class="head2" width="100%">
                <tr>
                    <td colspan="2">
                    <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia($data->tanggal_penawaran) . '</p>
                    </td>
                    <td style="vertical-align: top; text-align:right;">
                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">' . strtoupper(__('QTC.header.contract')) . '</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" width="80%">
                        <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6>
                    </td>
                    <td style="vertical-align: top; text-align:right;">' . '</td>
                </tr>
                <tr>
                    <td style="width:35%;vertical-align:top;">
                    <p style="font-size: 10px;">
                        <u>' . __('QTC.header.office') . ' :</u>
                        <br>
                        <span id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span>
                        <br>
                        <span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span>
                        <br>
                        <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . ' - ' . $data->no_pic_order . '</span>
                        <br>
                        <span id="email_pic_order">' . $data->email_pic_order . '</span>
                    </p>
                    </td>
                    <td style="width: 30%; text-align: center;"></td>
                    <td style="text-align: left;vertical-align:top;">
                    <p style="font-size: 10px;">
                        <u>' . __('QTC.header.sampling') . ' :</u>
                        <br>
                        <span id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span>
                        <br>
                        <span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span>
                        <br>
                        <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . '</span>
                        <br>
                        <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                    </p>
                    </td>
                </tr>
            </table> ');

            $getBody = self::renderBody($pdf, $data, $fileName, $detail, $lang);

            return $getBody;
        } catch (\Throwable $e) {
            dd($e);
        }
    }

    public function renderBody($pdf, $data, $fileName, $detail, $lang)
    {
        app()->setLocale($lang);
        Carbon::setLocale($lang);

        try {
            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">' . strtoupper(__('QTC.table.header.no')) . '</th>
                            <th width="62%">' . strtoupper(__('QTC.table.header.description')) . '</th>
                            <th width="12%">' . __('QTC.table.header.quantity') . '</th>
                            <th width="12%">' . strtoupper(__('QTC.table.header.unit_price')) . '</th>
                            <th width="12%">' . strtoupper(__('QTC.table.header.total_price')) . '</th>
                        </tr>
                    </thead>
                    <tbody>'
            );

            switch ($data->status_sampling) {
                case "S24":
                    $sampling = strtoupper(__('QTC.status_sampling.S24'));
                    break;
                case "SD":
                    $sampling = strtoupper(__('QTC.status_sampling.SD'));
                    break;
                default:
                    $sampling = strtoupper(__('QTC.status_sampling.S'));
                    break;
            }

            $konsultant = "";
            $jab_pic_or = "";
            $jab_pic_samp = "";

            if ($data->konsultan != '') {
                $konsultant = strtoupper(htmlspecialchars_decode($data->konsultan));
                $perusahaan = ' (' . strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan))) . ') ';
            } else {
                $perusahaan = strtoupper(htmlspecialchars_decode(strtolower($data->nama_perusahaan)));
            }

            $period = [];
            foreach ($detail as $key => $val) {
                if ($key == 0 || $key == count($detail) - 1) {
                    foreach (json_decode($val->data_pendukung_sampling) as $k_ => $v_) {
                        $period[] = self::tanggal_indonesia($v_->periode_kontrak, "period");
                    }
                }
            }

            if (explode(" ", $period[0])[1] === explode(" ", end($period))[1]) {
                $period = explode(" ", $period[0])[0] . " - " . end($period);
            } else {
                $period = $period[0] . " - " . end($period);
            }

            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);

                $penamaan_titik = "";

                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_unique(array_filter($a->penamaan_titik, function ($value) {
                            return $value != "" && $value != " " && $value != "-";
                        }));

                        if (!empty($filtered_array)) {
                            $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    } else {
                        $penamaan_titik = "(" . $a->penamaan_titik . ")";
                    }
                } else {
                    $penamaan_titik = "";
                }

                //  Hidupin untuk tampilkan penamaan titik
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                        <td style="font-size: 13px; padding: 5px;">
                            <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                            <hr>"
                );
                // $pdf->WriteHTML(
                //     ' <tr>
                //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                //         <td style="font-size: 13px; padding: 5px;">
                //             <b style="font-size: 13px;">' . $kategori2[1] . "</b>
                //             <hr>"
                // );
                if ($a->regulasi !== null && count($a->regulasi) > 0 && $a->regulasi[0] != "") {
                    foreach ($a->regulasi as $k => $v) {
                        $reg__ = '';

                        if ($v != '') {
                            $regulasi = array_slice(explode("-", $v), 1);
                            $reg__ = implode("-", $regulasi);
                        }

                        if ($k == 0) {
                            $pdf->WriteHTML('<u style="font-size: 13px;">' . $reg__ . "</u>");
                        } else {
                            $pdf->WriteHTML('<br><u style="font-size: 13px;">' . $reg__ . "</u>");
                        }
                    }
                }

                $akreditasi = [];
                $non_akre = [];
                foreach ($a->parameter as $keys => $values) {
                    $d = Parameter::where("id", explode(";", $values)[0])
                        ->where("is_active", 1)
                        ->first();

                    /* Hidupin untuk tampilkan akreditasi
                    if ($d->status == 'AKREDITASI') {
                        array_push($akreditasi, $d->nama_lab);
                    } else {
                        array_push($non_akre, $d->nama_lab);
                    }
                    if ($keys == 0) {
                        $pdf->WriteHTML(
                            ' <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                        );
                    }*/

                    if ($keys == 0) {
                        $pdf->WriteHTML(
                            ' <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    } else {
                        $pdf->WriteHTML(
                            ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                        );
                    }
                }
                $volume = "";
                if ($a->kategori_1 == "1-Air") {
                    $volume =
                        " - " . __('QTC.table.item.volume') . " : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                /* Hidupin untuk tampilkan akreditasi
                $pdf->WriteHTML(
                    " <br>
                    <hr>" . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">' . __('QTC.table.item.total_parameter') . ' : ' . count($a->parameter) . $volume . ' - KAN (P) : ' . count($akreditasi) . ' (' . count($non_akre) . ')' . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . (int) $a->jumlah_titik * count($a->periode) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan * ((int) $a->jumlah_titik * count($a->periode))) . '</td>
                    </tr>'
                );*/
                $pdf->WriteHTML(
                    " <br>
                    <hr>" . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">' . __('QTC.table.item.total_parameter') . ' : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . (int) $a->jumlah_titik * count($a->periode) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan * ((int) $a->jumlah_titik * count($a->periode))) . '</td>
                    </tr>'
                );

            }

            $wilayah = explode("-", $data->wilayah, 2);

            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.transport') . ' : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total) . '</td>
                    </tr>'
                );
            }

            $perdiem_24 = "";
            $total_perdiem = 0;

            if (
                $data->harga_24jam_personil_total > 0
            ) {
                $perdiem_24 = __('QTC.table.item.manpower24');
                $total_perdiem += $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.manpower') . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.expenses.other') . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_lain) . '</td>
                    </tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.expenses.preparation') . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_preparasi) . '</td>
                    </tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>' . __('QTC.terms_conditions.payment.title') . '</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            if (!is_null($syarat_ketentuan)) {
                // if ($syarat_ketentuan->pembayaran != null) { Update by Afryan at 2025-02-04 to handle pembayaran
                if (isset($syarat_ketentuan->pembayaran) && $syarat_ketentuan->pembayaran != null) {
                    if ($data->cash_discount_persen != null) {
                        $pdf->WriteHTML(
                            ' <br>
                            <span style="font-size: 10px !important;">' . __('QTC.terms_conditions.payment.cash_discount') . '</span>'
                        );
                    }
                    foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                        if (preg_match('/Pembayaran (\d+) Hari setelah Laporan Hasil Pengujian dan Invoice diterima lengkap oleh pihak pelanggan\./', $v, $matches)) {
                            $days = $matches[1];
                            $v = __('QTC.terms_conditions.payment.1', ['days' => $days]);
                        } else if (preg_match('/Pembayaran (\d+)% lunas sebelum sampling dilakukan\./', $v, $matches)) {
                            $percent = $matches[1];
                            $v = __('QTC.terms_conditions.payment.2', ['percent' => $percent]);
                        } else if (preg_match('/Masa berlaku penawaran (\d+) hari\./', $v, $matches)) {
                            $days = $matches[1];
                            $v = __('QTC.terms_conditions.payment.3', ['days' => $days]);
                        } else if (preg_match('/^Pembayaran Lunas saat sampling dilakukan oleh pihak pelanggan\.$/i', $v)) {
                            $v = __('QTC.terms_conditions.payment.4');
                        } else if (preg_match('/Pembayaran ([\d.,]+) Down Payment \(DP\), Pelunasan saat (.+)/i', $v, $matches)) {
                            $amount = $matches[1];
                            $text = $matches[2];
                            if ($lang != 'id') {
                                $tranlator = new Translator();
                                $text = $tranlator->translate($text, 'id', $lang);
                            }
                            $v = __('QTC.terms_conditions.payment.5', ['amount' => $amount, 'text' => $text]);
                        } else if (preg_match('/Pembayaran I sebesar ([\d.,]+), Pelunasan saat (.+)/i', $v, $matches)) {
                            $amount = $matches[1];
                            $text = $matches[2];
                            if ($lang != 'id') {
                                $tranlator = new Translator();
                                $text = $tranlator->translate($text, 'id', $lang);
                            }
                            $v = __('QTC.terms_conditions.payment.6', ['amount' => $amount, 'text' => $text]);
                        } else if (
                            preg_match(
                                '/Pembayaran dilakukan dalam (\d+) tahap, Tahap I sebesar ([\d.,]+), Tahap II sebesar ([\d.,]+), Tahap III sebesar ([\d.,]+) dari total order\./i',
                                $v,
                                $matches
                            )
                        ) {
                            $jumlahTahap = $matches[1];
                            $tahap1 = $matches[2];
                            $tahap2 = $matches[3];
                            $tahap3 = $matches[4];

                            $v = __('QTC.terms_conditions.payment.7', [
                                'count' => $jumlahTahap,
                                'amount1' => $tahap1,
                                'amount2' => $tahap2,
                                'amount3' => $tahap3
                            ]);
                        } else if (preg_match('/^Pembayaran (\d+)% DP, Pelunasan saat draft Laporan Hasil Pengujian diterima pelanggan\./', $v, $matches)) {
                            $percent = $matches[1];
                            $v = __('QTC.terms_conditions.payment.8', ['percent' => $percent]);
                        } else {
                            if ($lang != 'id') {
                                $tranlator = new Translator();
                                $v = $tranlator->translate($v, 'id', $lang);
                            }
                        }

                        $pdf->WriteHTML(
                            '<br><span style="font-size: 10px !important;">- ' . $v . "</span>"
                        );
                    }
                }
            }
            $pdf->WriteHTML(
                '</td>
                <td width="36%">
                    <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                        <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>' . strtoupper(__('QTC.total.sub')) . '</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->grand_total) . '</td>
                        </tr> '
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.water') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_non_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.non_water') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_non_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.air') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_udara) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.emission') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_emisi) . '</td>
                    </tr> '
                );
            }
            $diluar_pajak = $data->diluar_pajak ? json_decode($data->diluar_pajak) : null;
            if (!is_null($diluar_pajak)) {
                if ($data->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.transport') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr> '
                    );
                }
                if ($data->total_discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr> '
                    );
                }
                if ($data->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower24') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr> '
                    );
                }
            } else {
                if ($data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.transport') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr> '
                    );
                }
                if ($data->total_discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr> '
                    );
                }
                if ($data->total_discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower24') . '(%)</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr> '
                    );
                }
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.operational') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.consultant') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.group') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }
            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.percent') . '(%)</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->total_cash_discount > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.cash') . '</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_cash_discount) . '</td>
                    </tr> '
                );
            }

            if ($data->total_custom_discount > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.custom') . '</test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_custom_discount) . '</td>
                    </tr> '
                );
            }
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>' . strtoupper(__('QTC.total.after_discount')) . '</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }


            if ($data->total_ppn > 0 && $data->total_ppn != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . strtoupper(__('QTC.tax.vat')) . $data->ppn . '% </test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_ppn) . '</td>
                    </tr> '
                );
            }

            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . strtoupper(__('QTC.tax.income')) . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }

            if ($data->piutang !== $data->biaya_akhir) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;"><b>' . strtoupper(__('QTC.table.item.expenses.aftex_tax')) . '</b></td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->piutang) . '</td>
                    </tr> '
                );
            }

            if ($data->total_biaya_di_luar_pajak > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . strtoupper(__('QTC.table.item.expenses.non_taxable')) . '</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_biaya_di_luar_pajak) . '</td>
                    </tr> '
                );
            }

            if (!is_null($diluar_pajak)) {
                if ($diluar_pajak->transportasi == 'true' && $data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.non_taxable.transport') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr> '
                    );
                }

                if ($diluar_pajak->perdiem == 'true') {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.non_taxable.manpower') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr> '
                    );
                }

                if ($diluar_pajak->perdiem24jam == 'true') {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.non_taxable.manpower24') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr> '
                    );
                }
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>' . strtoupper(__('QTC.total.price')) . '</b>
                        </test>
                    <td style="text-align:right;padding:5px;">' . self::rupiah($data->biaya_akhir) . '</td>
                </tr> '
            );
            $pdf->WriteHTML("</table></td></tr></table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if ($data->keterangan_tambahan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="font-size: 10px;">
                            <u>
                                <b>' . __('QTC.terms_conditions.additional.title') . '</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
                    if ($lang != 'id') {
                        $v = str_replace('Kebisingan', 'Noise', $v);

                        $tranlator = new Translator();
                        $v = $tranlator->translate($v, 'id', $lang);
                    }

                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="font-size: 10px;">- ' . $v . "</td>
                        </tr>"
                    );
                }
            }
            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            if (!is_null($syarat_ketentuan)) {
                if ($syarat_ketentuan->umum != null) {
                    /* Hidupin untuk tampilkan akreditasi
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>' . __('QTC.terms_conditions.general.title') . '</b>
                                </u>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 10px;">' . __('QTC.terms_conditions.general.accreditation') . '</td>
                        </tr>'
                    );*/
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>' . __('QTC.terms_conditions.general.title') . '</b>
                                </u>
                            </td>
                        </tr>'
                    );
                    foreach ($syarat_ketentuan->umum as $k => $v) {
                        if (
                            $v !=
                            "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan"
                        ) {
                            if (preg_match('/^Untuk kategori Udara, <b>harga sudah termasuk<\/b> parameter <b>Suhu - Kecepatan Angin - Arah Angin - Kelembaban - Cuaca\.<\/b>$/', $v)) {
                                $v = __('QTC.terms_conditions.general.1');
                            } else if (preg_match('/^Sumber listrik disediakan oleh pihak pelanggan\.$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.2');
                            } else if (preg_match('/^Harga di atas untuk jumlah titik sampling yang tertera dan dapat berubah disesuaikan dengan kondisi lapangan dan permintaan pelanggan\.$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.3');
                            } else if (preg_match('/^Pembatalan atau penjadwalan ulang oleh pihak pelanggan akan dikenakan biaya transportasi dan\/atau perdiem\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.4');
                            } else if (preg_match('/^Pekerjaan akan dilaksanakan setelah pihak kami menerima konfirmasi berupa dokumen PO \/ SPK dari pihak pelanggan\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.5');
                            } else if (preg_match('/^Bagi perusahaan yang tidak menerbitkan PO \/ SPK, dapat menandatangani penawaran harga sebagai bentuk persetujuan pelaksanaan pekerjaan\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.6');
                            } else if (preg_match('/^Laporan Hasil Pengujian akan dikeluarkan dalam jangka waktu 10 hari kerja, terhitung sejak tanggal sampel diterima di laboratorium\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.7');
                            } else if (preg_match('/^Optimal perhari 1 \(satu\) tim sampling \(2 orang\) bisa mengerjakan 6 titik udara \(Ambient \/ Lingkungan Kerja\)\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.8');
                            } else if (preg_match('/^Jangka waktu pembuatan dokumen dikerjakan selama 2 - 3 bulan, dengan kewajiban pelanggan melengkapi dokumen sebelum sampling dilakukan\.?$/i', $v)) {
                                $v = __('QTC.terms_conditions.general.9');
                            } else if (preg_match('/^Biaya sudah termasuk (.+)$/i', $v, $matches)) {
                                $text = $matches[1];
                                if ($lang != 'id') {
                                    $tranlator = new Translator();
                                    $text = $tranlator->translate($text, 'id', $lang);
                                }
                                $v = __('QTC.terms_conditions.general.10', [
                                    'costs' => $text
                                ]);
                            } else {
                                if ($lang != 'id') {
                                    $tranlator = new Translator();
                                    $v = $tranlator->translate($v, 'id', $lang);
                                }
                            }

                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="font-size: 10px;">- ' . $v . "</td>
                                </tr>"
                            );
                        }
                    }
                }
            }

            $pdf->WriteHTML("</table>");
            $pdf->WriteHTML('<table width="100%" style="line-height: 2;">');
            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;font-size: 10px;" colspan="2">
                        <b>
                            <u>' . __('QTC.approval.proof') . '</u>
                        </b>
                    </td>
                </tr>'
            );
            $add = "-";
            $updd = "-";
            $app = "-";
            $no_telp = "";

            $add = ($data->sales->nama_lengkap != null) ? $data->sales->nama_lengkap : "-";
            $no_telp = ($data->sales->no_telpon != null) ? " (" . $data->sales->no_telpon . ")" : "";
            $updd = ($data->updated_by != null) ? $data->updated_by : "-";
            $st = ($data->is_approved == 0) ? "NOT APPROVED" : "APPROVED";

            $pdf->WriteHTML(
                ' <tr>
                    <td>
                        <span style="font-size:11px;">' . __('QTC.approval.administration') . ' : ' . $updd . '</span>
                        <br>
                        <span style="font-size:11px;">' . __('QTC.approval.status') . ' : ' . $st . '</span>
                        <br>
                        <span style="font-size:11px;">' . __('QTC.approval.pic') . ' : ' . $add . $no_telp . '</span>
                        <br>
                    </td>
                    <td style="font-size: 10px;text-align:center;">
                        <span>' . __('QTC.approval.approving') . ',</span>
                        <br>
                        <br>
                        <br>
                        <span>' . __('QTC.approval.name') . ' (..............................................)</span>
                        <br>
                        <span>' . __('QTC.approval.position') . ' (..............................................)</span>
                    </td> '
            );
            $pdf->WriteHTML("</tr></table>");

            $per = [];

            foreach ($detail as $k => $v) {
                foreach (json_decode($v->data_pendukung_sampling) as $key => $value) {

                    array_push($per, $value->periode_kontrak);
                    switch ($v->status_sampling) {
                        case "S24":
                            $sampling = strtoupper(__('QTC.status_sampling.S24'));
                            break;
                        case "SD":
                            $sampling = strtoupper(__('QTC.status_sampling.SD'));
                            break;
                        default:
                            $sampling = strtoupper(__('QTC.status_sampling.S'));
                            break;
                    }
                    $pdf->SetHTMLHeader(
                        ' <table class="tabel" width="100%">
                            <tr class="tr_top">
                                <td class="text-left text-wrap" style="width: 33.33%;">
                                    <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                                </td>
                                <td style="width: 33.33%; text-align: center;">
                                    <h5 style="text-align:center; font-size:14px;">
                                        <b>
                                            <u>' . strtoupper(__('QTC.header.quotation')) . '</u>
                                        </b>
                                    </h5>
                                    <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
                                    <p style="font-size: 10px;text-align:center;">' . self::tanggal_indonesia($value->periode_kontrak, "period") . '</p>
                                </td>
                                <td style="text-align: right;">
                                    <p style="font-size: 9px; text-align:right;">
                                        <b>PT INTI SURYA LABORATORIUM</b>
                                        <br>
                                        <span style="white-space: pre-wrap; word-wrap: break-word;">' . $data->cabang->alamat_cabang . "</span>
                                        <br>
                                        <span>T : " . $data->cabang->tlp_cabang . ' - sales@intilab.com</span>
                                        <br>www.intilab.com
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <table class="head2" width="100%">
                            <tr>
                                <td colspan="2">
                                    <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia($data->tanggal_penawaran) . '</p>
                                </td>
                                <td style="vertical-align: top; text-align:right;">
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;">' . strtoupper(__('QTC.header.contract')) . '</span>
                                    <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-top:5px;" id="status_sampling">' . $sampling . '</span>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" width="80%">
                                    <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . $perusahaan . '</h6>
                                </td>
                                <td style="vertical-align: top; text-align:right;">' . '</td>
                            </tr>
                            <tr>
                                <td style="width:35%;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>' . __('QTC.header.office') . ' :</u>
                                        <br>
                                        <span id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span>
                                        <br>
                                        <span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span>
                                        <br>
                                        <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . " - " . $data->no_pic_order . '</span>
                                        <br>
                                        <span id="email_pic_order">' . $data->email_pic_order . '</span>
                                    </p>
                                </td>
                                <td style="width: 30%; text-align: center;"></td>
                                <td style="text-align: left;vertical-align:top;">
                                    <p style="font-size: 10px;">
                                        <u>' . __('QTC.header.sampling') . ' :</u>
                                        <br>
                                        <span id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span>
                                        <br>
                                        <span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span>
                                        <br>
                                        <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . '</span>
                                        <br>
                                        <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                                    </p>
                                </td>
                            </tr>
                        </table> '
                    );

                    $pdf->AddPage();

                    $pdf->WriteHTML(
                        ' <table class="table table-bordered" style="font-size:8px;border:1px solid;border-color:#000">
                            <thead class="text-center">
                                <tr style="page-break-inside:avoid">
                                    <th width="2%">' . strtoupper(__('QTC.table.header.no')) . '</th>
                                    <th width="60%" class="text-center">' . strtoupper(__('QTC.table.header.description')) . '</th>
                                    <th>' . __('QTC.table.header.quantity') . '</th>
                                    <th>' . strtoupper(__('QTC.table.header.unit_price')) . '</th>
                                    <th>' . strtoupper(__('QTC.table.header.total_price')) . '</th>
                                </tr>
                            </thead>
                            <tbody>'
                    );

                    $i = 1;
                    if (!is_null($value->data_sampling)) {
                        if (is_array($value->data_sampling)) {
                            $dataSampling = $value->data_sampling;
                        } else {
                            $dataSampling = json_decode($value->data_sampling);
                        }
                        foreach ($dataSampling as $b => $a) {
                            $kategori = explode("-", $a->kategori_1);
                            $kategori2 = explode("-", $a->kategori_2);
                            $penamaan_titik = "";
                            if (is_array($a->penamaan_titik)) {
                                $penamaan_titik_strings = array_map(function ($item) {
                                    if (is_object($item)) {
                                        $props = get_object_vars($item);
                                        return reset($props);
                                    }
                                    return $item;
                                }, $a->penamaan_titik);

                                $penamaan_titik = "(" . implode(", ", $penamaan_titik_strings) . ")";
                            } else {
                                if (is_object($a->penamaan_titik)) {
                                    $props = get_object_vars($a->penamaan_titik);
                                    $penamaan_titik = "(" . reset($props) . ")";
                                } else {
                                    $penamaan_titik = "(" . $a->penamaan_titik . ")";
                                }
                            }

                            if (!empty($a->penamaan_titik)) {
                                if (is_array($a->penamaan_titik)) {
                                    $filtered_array = array_filter($a->penamaan_titik, function ($value) {
                                        if (is_object($value)) {
                                            $props = get_object_vars($value);
                                            return !empty($props) && trim(reset($props)) !== '' && trim(reset($props)) !== '-';
                                        }
                                        return $value != "" && $value != " " && $value != "-";
                                    });

                                    if (!empty($filtered_array)) {
                                        $penamaan_titik_strings = array_map(function ($item) {
                                            if (is_object($item)) {
                                                $props = get_object_vars($item);
                                                return reset($props); 
                                            }
                                            return $item;
                                        }, $filtered_array);

                                        $penamaan_titik = "(" . implode(", ", $penamaan_titik_strings) . ")";
                                    } else {
                                        $penamaan_titik = "";
                                    }
                                } else {
                                    if (is_object($a->penamaan_titik)) {
                                        $props = get_object_vars($a->penamaan_titik);
                                        $penamaan_titik = "(" . reset($props) . ")";
                                    } else {
                                        $penamaan_titik = "(" . $a->penamaan_titik . ")";
                                    }
                                }
                            } else {
                                $penamaan_titik = "";
                            }

                            $filtered = array_filter($a->penamaan_titik, function ($item) {
                                $props = get_object_vars($item);
                                $key = key($props);
                                $value = $props[$key];

                                return !empty($value);
                            });

                            $resultParts = array_map(function ($item) {
                                $props = get_object_vars($item);
                                $key = key($props);
                                $value = $props[$key];

                                return $value;
                            }, $filtered);


                            $penamaan_titik = count($resultParts) > 0 ? "(" . implode(', ', array_unique($resultParts)) . ")" : '';

                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                    <td style="font-size: 13px; padding: 5px;">
                                        <b style="font-size: 13px;">' . $kategori2[1] . " " . $penamaan_titik . "</b>
                                        <hr>"
                            );

                            // $pdf->WriteHTML(
                            //     ' <tr>
                            //         <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            //         <td style="font-size: 13px; padding: 5px;">
                            //             <b style="font-size: 13px;">' . $kategori2[1] . "</b>
                            //             <hr>"
                            // );

                            if ($a->regulasi !== "" && $a->regulasi != null && count($a->regulasi) > 0 && $a->regulasi[0] != "") {
                                foreach ($a->regulasi as $k => $z) {
                                    $reg__ = '';
                                    if ($z != '') {
                                        $regulasi = array_slice(explode("-", $z), 1);
                                        $reg__ = implode("-", $regulasi);
                                    }
                                    if ($k == 0) {
                                        $pdf->WriteHTML(
                                            '<u style="font-size: 13px;">' . $reg__ . "</u>"
                                        );
                                    } else {
                                        $pdf->WriteHTML(
                                            '<br>
                                            <u style="font-size: 13px;">' . $reg__ . "</u>"
                                        );
                                    }
                                }
                            }

                            $akreditasi_detail = [];
                            $non_akre_detail = [];
                            foreach ($a->parameter as $keys => $valuess) {
                                $dParam = explode(";", $valuess);
                                $d = Parameter::where("id", $dParam[0])
                                    ->where("is_active", true)
                                    ->first();

                                /* Hidupin untuk tampilkan akreditasi
                                if ($d->status == 'AKREDITASI') {
                                    array_push($akreditasi, $d->nama_lab);
                                } else {
                                    array_push($non_akre, $d->nama_lab);
                                }
                                if ($keys == 0) {
                                    $pdf->WriteHTML(
                                        ' <br>
                                        <hr>
                                        <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                                    );
                                } else {
                                    $pdf->WriteHTML(
                                        ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . ($d->status == 'AKREDITASI' ? '<b>' . $d->nama_regulasi . '</b>' : $d->nama_regulasi . '<sup style="font-size: 14px;"><u>x</u></sup>') . '</span>'
                                    );
                                }*/

                                if ($keys == 0) {
                                    $pdf->WriteHTML(
                                        ' <br>
                                        <hr>
                                        <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                                    );
                                } else {
                                    $pdf->WriteHTML(
                                        ' &bull; <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span> "
                                    );
                                }
                            }

                            $volume = (explode("-", $a->kategori_1)[1] == "Air")
                                ? " - " . __('QTC.table.item.volume') . " : " . number_format($a->volume / 1000, 1) . " L"
                                : "";

                            /* Hidupin untuk tampilkan akreditasi
                            $pdf->WriteHTML(
                                " <br>
                                <hr>" . ' <b>
                                    <span style="font-size: 13px; margin-top: 5px;">' . __('QTC.table.item.volume') . ' : ' . count($a->parameter) . $volume . ' - KAN (P) : ' . count($akreditasi_detail) . ' (' . count($non_akre_detail) . ')' . '</span>
                                </b>
                                </td>
                                <td style="vertical-align: middle;text-align:center;font-size: 13px; padding: 5px;">' . $a->jumlah_titik . '</td>
                                <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . self::rupiah($a->harga_satuan) . '</td>
                                <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . self::rupiah($a->harga_total) . '</td>
                                </tr>'
                            );*/

                            $pdf->WriteHTML(
                                " <br>
                                <hr>" . ' <b>
                                    <span style="font-size: 13px; margin-top: 5px;">' . __('QTC.table.item.volume') . ' : ' . count($a->parameter) . $volume . '</span>
                                </b>
                                </td>
                                <td style="vertical-align: middle;text-align:center;font-size: 13px; padding: 5px;">' . $a->jumlah_titik . '</td>
                                <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . self::rupiah($a->harga_satuan) . '</td>
                                <td style="vertical-align: middle;text-align:right;font-size: 13px; padding: 5px;">' . self::rupiah($a->harga_total) . '</td>
                                </tr>'
                            );
                        }
                    }
                }

                if ($v->transportasi > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.transport') . ' : ' . $wilayah[1] . '</td>
                            <td style="font-size: 13px; text-align:center;">' . $v->transportasi . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($v->harga_transportasi) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($v->harga_transportasi * $v->transportasi) . '</td>
                        </tr>'
                    );
                }
                $perdiem_24 = "";
                $total_perdiem = 0;
                if (
                    $v->jumlah_orang_24jam > 0 &&
                    $v->jumlah_orang_24jam != ""
                ) {
                    $perdiem_24 = __('QTC.table.item.manpower24');
                    $total_perdiem =
                        $total_perdiem + $v->{'harga_24jam_personil_total'};
                }
                if ($v->perdiem_jumlah_orang > 0) {
                    $i = $i + 1;
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.manpower') . $perdiem_24 . '</td>
                            <td style="font-size: 13px; text-align:center;"></td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                            <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($v->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        </tr>'
                    );
                }

                if ($v->total_biaya_lain > 0) {
                    $biaya_lain = json_decode($v->biaya_lain);
                    if ($biaya_lain != null) {
                        $i = $i + 1;
                        foreach ($biaya_lain as $s => $h) {
                            if ($lang != 'id') {
                                $h->deskripsi = str_replace('Perdiem', 'Manpower', $h->deskripsi);
                                $tranlator = new Translator();
                                $h->deskripsi = $tranlator->translate($h->deskripsi, 'id', $lang);
                            }

                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                    <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.expenses.cost') . ' : ' . $h->deskripsi . '</td>
                                    <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                                    <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($h->harga) . '</td>
                                    <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($h->harga) . '</td>
                                </tr>'
                            );
                        }
                    }
                }

                $biaya_preparasi = json_decode($v->biaya_preparasi);
                if (count($biaya_preparasi) > 0) {
                    $i = $i + 1;
                    foreach ($biaya_preparasi as $s => $h) {
                        if ($lang != 'id') {
                            $h->deskripsi = str_replace('Perdiem', 'Manpower', $h->deskripsi);
                            $tranlator = new Translator();
                            $h->deskripsi = $tranlator->translate($h->deskripsi, 'id', $lang);
                        }

                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                                <td style="font-size: 13px;padding: 5px;">' . __('QTC.table.item.expenses.preparation') . ' : ' . $h->Deskripsi . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($h->Harga) . '</td>
                                <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($h->Harga) . '</td>
                            </tr>'
                        );
                    }
                }
                $pdf->WriteHTML("</tbody></table>");

                $pdf->WriteHTML(
                    ' <table width="100%" style="line-height: 2;">
                        <tr>
                            <td style="font-size: 10px;vertical-align: top;" width="62%">'
                );
                $pdf->WriteHTML(
                    '</td>
                    <td width="38%">
                        <table class="table table-bordered" width="100%" style="font-size: 11px; margin-right: -4px;">
                            <tr>
                                <td style="text-align:center;padding:5px;">
                                    <b>' . strtoupper(__('QTC.total.sub')) . '</b>
                                </td>
                                <td style="text-align:right;padding:5px;" width="39%">' . self::rupiah($v->grand_total) . '</td>
                            </tr> '
                );
                if (
                    $v->discount_air != null &&
                    $v->total_discount_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.water') . $v->discount_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_non_air != null &&
                    $v->total_discount_non_air != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.non_water') . $v->discount_non_air . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_non_air) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_udara != null &&
                    $v->total_discount_udara != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.air') . $v->discount_udara . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_udara) . '</td>
                        </tr> '
                    );
                }
                if (
                    $v->discount_emisi != null &&
                    $v->total_discount_emisi != "0.00"
                ) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.emission') . $v->discount_emisi . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_emisi) . '</td>
                        </tr> '
                    );
                }

                if (!is_null($diluar_pajak)) {
                    if ($v->discount_transport != null && $v->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.transport') . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->discount_perdiem != null && $v->discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower') . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->total_discount_perdiem_24jam != null && $v->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower24') . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                } else {
                    if ($v->discount_transport != null && $v->total_discount_transport > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.transport') . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->discount_perdiem != null && $v->total_discount_perdiem > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower') . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }
                    if ($v->total_discount_perdiem_24jam != null && $v->total_discount_perdiem_24jam > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower24') . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                }

                if ($v->total_discount_gabungan != null && $v->total_discount_gabungan > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.operational') . trim(str_replace('%', '', $v->discount_gabungan)) . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_gabungan) . '</td>
                        </tr> '
                    );
                }
                if ($v->total_discount_consultant != null && $v->total_discount_consultant > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.consultant') . $v->discount_consultant . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_consultant) . '</td>
                        </tr> '
                    );
                }
                if ($v->total_discount_group != null && $v->total_discount_group > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.group') . $v->discount_group . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_group) . '</td>
                        </tr> '
                    );
                }

                if ($v->total_cash_discount_persen != null && $v->total_cash_discount_persen > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.disc') . $v->cash_discount_persen . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_cash_discount_persen) . '</td>
                        </tr> '
                    );
                }
                if ($v->cash_discount != null && $v->cash_discount != 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.disc') . '</test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->cash_discount) . '</td>
                        </tr> '
                    );
                }

                if ($v->custom_discount != null && $v->total_custom_discount > 0) {
                    $custom_disc = json_decode($v->custom_discount);
                    foreach ($custom_disc as $key => $value) {
                        if ($value->discount != null && $value->discount != 0) {
                            if ($lang != 'id') {
                                $value->deskripsi = str_replace('Perdiem', 'Manpower', $value->deskripsi);
                                $tranlator = new Translator();
                                $value->deskripsi = $tranlator->translate($value->deskripsi, 'id', $lang);
                            }

                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $value->deskripsi . '</test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($value->discount) . '</td>
                                </tr> '
                            );
                        }
                    }
                }

                if ($v->total_dpp != $v->grand_total) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>' . strtoupper(__('QTC.total.after_discount')) . '</b>
                                </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_dpp) . '</td>
                        </tr>'
                    );
                }

                if ($v->total_ppn > 0 && $v->total_ppn != "") {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . strtoupper(__('QTC.tax.vat')) . $v->ppn . '% </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_ppn) . '</td>
                        </tr> '
                    );
                }

                if ($v->total_pph > 0 && $v->total_pph != "") {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . strtoupper(__('QTC.tax.income')) . $v->pph . '%</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_pph) . '</td>
                        </tr> '
                    );
                }

                if ($v->piutang !== $v->biaya_akhir) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>' . strtoupper(__('QTC.total.after_tax')) . '</b>
                                </test>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->piutang) . '</td>
                        </tr> '
                    );
                }

                if (!is_null($diluar_pajak)) {
                    $y = json_decode($v->biaya_di_luar_pajak);
                    // if ($y->body != null || $y->select) {
                    // }

                    if ($y->body != null || $y->select) {
                        if ($y->select != null) {
                            foreach ($y->select as $k => $c) {
                                if ($c->harga != null || $c->harga != 0) {
                                    if ($lang != 'id') {
                                        $c->deskripsi = str_replace('Perdiem', 'Manpower', $c->deskripsi);
                                        $tranlator = new Translator();
                                        $c->deskripsi = $tranlator->translate($c->deskripsi, 'id', $lang);
                                    }

                                    $pdf->WriteHTML(
                                        ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                                </tr> '
                                    );
                                }
                            }
                        }

                        if ($y->body != null) {
                            foreach ($y->body as $k => $c) {
                                if ($c->harga != null || $c->harga != 0) {
                                    if ($lang != 'id') {
                                        $c->deskripsi = str_replace('Perdiem', 'Manpower', $c->deskripsi);
                                        $tranlator = new Translator();
                                        $c->deskripsi = $tranlator->translate($c->deskripsi, 'id', $lang);
                                    }

                                    $pdf->WriteHTML(
                                        ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . ' </test>
                                    <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                                </tr> '
                                    );
                                }
                            }
                        }
                    }
                    if ($diluar_pajak->transportasi == 'true' && $v->total_discount_transport > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.transport') . $v->discount_transport . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_transport) . '</td>
                            </tr> '
                        );
                    }

                    if ($diluar_pajak->perdiem == 'true' && $v->total_discount_perdiem > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower') . $v->discount_perdiem . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem) . '</td>
                            </tr> '
                        );
                    }

                    if ($diluar_pajak->perdiem24jam == 'true' && $v->total_discount_perdiem_24jam > 0) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . __('QTC.discount.contract.manpower24') . $v->discount_perdiem_24jam . '% </test>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($v->total_discount_perdiem_24jam) . '</td>
                            </tr> '
                        );
                    }
                }

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>' . strtoupper(__('QTC.total.price')) . '</b>
                            </test>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($v->biaya_akhir) . '</td>
                    </tr> '
                );

                $pdf->WriteHTML("</table></td></tr></table>");
            }

            $pdf->SetHTMLHeader(
                ' <table width="100%">
                    <tr>
                        <td class="text-left text-wrap">
                            <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                        </td>
                        <td style="width: 60%; text-align: center;">
                            <h5 style="text-align:center; font-size:11px;">
                                <b>
                                    <u>' . __('QTC.summary.header.title') . ' : ' . $period . '</u>
                                </b>
                            </h5>
                            <p style="font-size: 10px;text-align:center;">
                                <b>' . $konsultant . $perusahaan . '</b>
                            </p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 9px; text-align:right;">' . __('QTC.summary.header.contract') . ' : ' . $data->no_document . ' </p>
                            <p style="font-size: 9px; text-align:right;">
                                <b>' . strtoupper(__('QTC.summary.header.pic')) . '<b> : ' . $add . '
                            </p>
                        </td>
                    </tr>
                </table> '
            );

            $pdf->AddPage("L");
            $pdf->SetWatermarkImage(public_path() . "/logo-watermark.png", -1, "", [110, 35]);

            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;" width="100%">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;" rowspan="2">' . strtoupper(__('QTC.table.header.no')) . '</th>
                            <th width="25%" rowspan="2">' . strtoupper(__('QTC.table.header.description')) . '</th>'
            );
            $a = 1;
            foreach ($per as $c) {
                $pdf->WriteHTML("<th>" . $a++ . "</th>");
            }
            $pdf->WriteHTML(
                ' <th rowspan="2">' . strtoupper(__('QTC.total.total')) . '</th>
                <th rowspan="2">' . strtoupper(__('QTC.table.header.unit_price')) . '</th>
                <th rowspan="2">' . strtoupper(__('QTC.total.price')) . '</th></tr><tr>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML("<th>" . Carbon::parse($c)->translatedFormat('M-y') . "</th>");
            }
            $pdf->WriteHTML("</tr></thead><tbody>");
            $i = 1;
            $t = count(json_decode($data->data_pendukung_sampling, true));
            $katgor = [];
            $x_ = 0;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                array_push($katgor, $a->kategori_2);

                // if (is_array($a->penamaan_titik)) {
                //     $penamaan_titik = "(" . implode(", ", $a->penamaan_titik) . ")";
                // } else {
                //     $penamaan_titik = "(" . $a->penamaan_titik . ")";
                // }
                $x_ = $key;

                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_unique(array_filter($a->penamaan_titik, function ($value) {
                            return $value != "" && $value != " " && $value != "-";
                        }));

                        if (!empty($filtered_array)) {
                            $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                        } else {
                            $penamaan_titik = "";
                        }
                    } else {
                        $penamaan_titik = "(" . $a->penamaan_titik . ")";
                    }
                } else {
                    $penamaan_titik = "";
                }

                $th_left = implode(' ', [
                    strtoupper(explode("-", $a->kategori_1)[1]),
                    strtoupper(explode("-", htmlspecialchars_decode($a->kategori_2))[1]),
                    $a->jumlah_titik,
                    $x_,
                    // $penamaan_titik,
                    $a->total_parameter,
                    implode(" ", $a->parameter)
                ]);

                $th_left1 = strtoupper(explode("-", $a->kategori_2)[1]);
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i++ . '</td>
                        <td style="font-size: 8px; padding: 5px;">
                            <b>' . $th_left1 . "</b>
                        </td>"
                );
                foreach ($detail as $key => $value) {
                    foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                        if (is_array($values->data_sampling)) {
                            $num_ = $values->data_sampling;
                        } else {
                            $num_ = json_decode($values->data_sampling);
                        }
                        // dd($num_);
                        $bollean = false;
                        $periode_found = [];
                        foreach ($num_ as $key_ => $val_) {
                            if ($val_->penamaan_titik && $val_->penamaan_titik != "") {
                                if (is_array($val_->penamaan_titik)) {
                                    $filtered_array = array_filter($val_->penamaan_titik, function ($item) {
                                        $props = get_object_vars($item);
                                        $value = current($props);
                                        return !empty($value);
                                    });

                                    $filtered_array = array_map(function ($item) {
                                        return current(get_object_vars($item));
                                    }, $filtered_array);

                                    if (!empty($filtered_array)) {
                                        $penamaan_titik = "(" . implode(", ", $filtered_array) . ")";
                                    } else {
                                        $penamaan_titik = "";
                                    }
                                } else {
                                    $penamaan_titik = "(" . $val_->penamaan_titik . ")";
                                }
                            } else {
                                $penamaan_titik = "";
                            }

                            // $penamaan_titik = "";
                            // $filtered = array_filter($val_->penamaan_titik, function ($item) {
                            //     $props = get_object_vars($item);
                            //     $key = key($props);
                            //     $value = $props[$key];

                            //     return !empty($value);
                            // });

                            // // ubah jadi string "Inlet (001), Outlet (002), ..."
                            // $resultParts = array_map(function ($item) {
                            //     $props = get_object_vars($item);
                            //     $key = key($props);
                            //     $value = $props[$key];

                            //     return $value;
                            // }, $filtered);

                            // $penamaan_titik = count($resultParts) > 0 ? "(" . implode(', ', $resultParts) . ")" : '';

                            $td_kat = implode(" ", [
                                strtoupper(explode("-", $val_->kategori_1)[1]),
                                strtoupper(explode("-", htmlspecialchars_decode($val_->kategori_2))[1]),
                                $val_->jumlah_titik,
                                $x_,
                                // $penamaan_titik,
                                $val_->total_parameter,
                                implode(" ", $val_->parameter)
                            ]);
                            $td_kat1 = strtoupper(
                                explode("-", $a->kategori_2)[1]
                            );

                            // dump($th_left, $td_kat);
                            if (in_array($values->periode_kontrak, $a->periode) && !in_array($values->periode_kontrak, $periode_found) && $th_left == $td_kat) {
                                $periode_found[] = $values->periode_kontrak;
                                $pdf->WriteHTML(
                                    '<td style="font-size: 8px; text-align:center;">' . $val_->jumlah_titik . "</td>"
                                );
                                $bollean = true;
                            }
                        }
                        if ($bollean == false) {
                            $pdf->WriteHTML("<td></td>");
                        }
                    }
                }

                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:center;">' . (int) $a->jumlah_titik * count($a->periode) . "</td>"
                );
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($a->harga_satuan) . "</td>"
                );
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($a->harga_satuan * ((int) $a->jumlah_titik * count($a->periode))) . "</td>"
                );
                $pdf->WriteHTML("</tr>");
                $x_++;
            }

            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">' . strtoupper(__('QTC.table.item.transport')) . ' : ' . $wilayah[1] . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;">' . $v->transportasi . "</td>"
                    );
                    $enum_ = $v->harga_transportasi;
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;">' . $data->transportasi . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total / $data->transportasi) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi_total) . '</td></tr>'
                );
            }

            $total_perdiem = 0;
            $perdiem_24 = $data->harga_24jam_personil_total > 0
                ? __('QTC.table.item.manpower24')
                : "";
            $total_perdiem += $data->harga_24jam_personil_total > 0
                ? $data->{'harga_24jam_personil_total'}
                : 0;

            if ($data->perdiem_jumlah_orang > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">' . __('QTC.table.item.manpower') . $perdiem_24 . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td></tr>'
                );
            }

            if ($data->total_biaya_lain > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">' . __('QTC.table.item.expenses.other') . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_lain) . '</td></tr>'
                );
            }

            if ($data->total_biaya_preparasi > 0) {
                $i = $i + 1;
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 8px;">' . $i . '</td>
                        <td style="font-size: 8px;padding: 5px;">' . __('QTC.table.item.expenses.preparation') . "</td>"
                );
                foreach ($detail as $k => $v) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:center;"></td>'
                    );
                }

                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:center;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;"></td>
                    <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($data->total_biaya_preparasi) . '</td></tr>'
                );
            }

            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                ' <table class="table table-bordered" style="font-size: 8px;margin-top:10px;">
                    <thead class="text-center">
                        <tr>
                            <th style="font-size: 8px;">' . strtoupper(__('QTC.summary.header.price')) . '</th>'
            );
            foreach ($per as $c) {
                $pdf->WriteHTML(
                    '<th style="font-size: 8px;">' . Carbon::parse($c)->translatedFormat('M-y') . "</th>"
                );
            }

            $pdf->WriteHTML(
                ' <th style="font-size: 8px;">' . strtoupper(__('QTC.total.price')) . '</th></tr></thead><tbody>'
            );

            // TOTAL ANALISA
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.total.analysis')) . '</td>'
            );
            $total_harga_analisa = 0;
            foreach ($detail as $key => $value) {
                foreach (json_decode($value->data_pendukung_sampling) as $keys => $values) {
                    $tot_harga = 0;
                    if (is_array($values->data_sampling)) {
                        foreach ($values->data_sampling as $b => $a) {
                            $tot_harga = $tot_harga + $a->harga_total;
                        }
                    } else {
                        foreach (json_decode($values->data_sampling) as $b => $a) {
                            $tot_harga = $tot_harga + $a->harga_total;
                        }
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($tot_harga) . "</td>"
                    );
                    $total_harga_analisa = $total_harga_analisa + $tot_harga;
                }
            }
            $pdf->WriteHTML(
                ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_analisa) . "</td></tr>"
            );

            // TOTAL TRANSPORT
            if ($data->transportasi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.total.transport')) . '</td>'
                );
                $total_harga_transport = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->harga_transportasi_total) . "</td>"
                    );
                    $total_harga_transport =
                        $total_harga_transport + $value->harga_transportasi_total;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_transport) . "</td></tr>"
                );
            }

            // TOTAL PERDIEM 24 JAM
            $perdiem_24 = "";
            $total_perdiem = 0;
            if ($data->harga_24jam_personil_total != 0) {
                $perdiem_24 = strtoupper(__('QTC.table.item.manpower24'));
                // $pdf->WriteHTML('<tr><td style="text-align:center;font-size: 8px;">TOTAL PERDIEM 24 JAM</td>');
                $total_harga_24jam_perdiem = 0;
                foreach ($detail as $key => $value) {
                    // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . Self::rupiah($value->harga_24jam_personil_total) . '</td>');
                    $total_harga_24jam_perdiem =
                        $total_harga_24jam_perdiem +
                        $value->harga_24jam_personil_total;
                }
                $total_perdiem = $total_perdiem + $total_harga_24jam_perdiem;
                // $pdf->WriteHTML('<td style="font-size: 8px; text-align:right; padding: 5px;">' . Self::rupiah($total_harga_24jam_perdiem) . '</td></tr>');
            }

            if ($data->perdiem_jumlah_orang > 0 || $data->jumlah_orang_24jam > 0) {
                // TOTAL PERDIEM
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.total.manpower')) . $perdiem_24 . "</td>"
                );
                $total_harga_perdiem = 0;
                foreach ($detail as $key => $value) {
                    $harga_perdiem = 0;
                    if ($value->jumlah_orang_24jam > 0) {
                        $harga_perdiem =
                            $harga_perdiem + $value->harga_24jam_personil_total;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->harga_perdiem_personil_total + $harga_perdiem) . "</td>"
                    );
                    $total_harga_perdiem =
                        $total_harga_perdiem + $value->harga_perdiem_personil_total;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_perdiem + $total_perdiem) . "</td></tr>"
                );
            }

            // BIAYA LAIN-LAIN
            if ($data->total_biaya_lain > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.table.item.expenses.other')) . '</td>'
                );
                $total_biaya_lain = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_lain) . "</td>"
                    );
                    $total_biaya_lain += $value->total_biaya_lain;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_lain) . "</td></tr>"
                );
            }

            // BIAYA PREPARASI
            if ($data->total_biaya_preparasi > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.table.item.expenses.preparation')) . '</td>'
                );
                $total_biaya_preparasi = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_preparasi) . "</td>"
                    );
                    $total_biaya_preparasi += $value->total_biaya_preparasi;
                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_preparasi) . "</td></tr>"
                );
            }

            // TOTAL HARGA PENGUJIAN
            $pdf->WriteHTML(
                '<tr>
                <td style="text-align:center;font-size: 8px;"><b>' . strtoupper(__('QTC.total.analysis_price')) . '</b></td>'
            );
            $total_harga_pengujian = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->grand_total) . "</b></td>"
                );
                $total_harga_pengujian =
                    $total_harga_pengujian + $value->grand_total;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_harga_pengujian) . "</b></td></tr>"
            );

            // DISCOUNT AIR
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.water')) . '(%)</td>'
                );
                $total_harga_discount_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_air) . "</td>"
                    );
                    $total_harga_discount_air =
                        $total_harga_discount_air + $value->total_discount_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_air) . "</td></tr>"
                );
            }
            // // DISCOUNT NON AIR
            if ($data->total_discount_non_air > 0) {

                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.non_water')) . '(%)</td>'
                );
                $total_harga_discount_non_air = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_non_air) . "</td>"
                    );
                    $total_harga_discount_non_air += $value->total_discount_non_air;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_non_air) . "</td></tr>"
                );
            }

            // DISCOUNT UDARA
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.air')) . '(%)</td>'
                );
                $total_harga_discount_udara = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_udara) . "</td>"
                    );
                    $total_harga_discount_udara += $value->total_discount_udara;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_udara) . "</td></tr>"
                );
            }

            // DISCOUNT EMISI
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.emission')) . '(%)</td>'
                );
                $total_harga_discount_emisi = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_emisi) . "</td>"
                    );
                    $total_harga_discount_emisi =
                        $total_harga_discount_emisi +
                        $value->total_discount_emisi;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_emisi) . "</td></tr>"
                );
            }

            if (!is_null($diluar_pajak)) {
                //Diskon TRANSPORT
                if ($data->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.transport')) . '(%)</td>'
                    );
                    $total_harga_discount_transport = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_harga_discount_transport =
                            $total_harga_discount_transport +
                            $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_transport) . "</td></tr>"
                    );
                }
                //Diskon PERDIEM
                if ($data->total_discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower')) . '(%)</td>'
                    );
                    $total_harga_discount_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_harga_discount_perdiem =
                            $total_harga_discount_perdiem +
                            $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_perdiem) . "</td></tr>"
                    );
                }
                //Diskon Perdiem 24 Jam
                if ($data->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower24')) . '(%)</td>'
                    );
                    $total_harga_discount__perdiem24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_harga_discount__perdiem24 =
                            $total_harga_discount__perdiem24 +
                            $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount__perdiem24) . "</td></tr>"
                    );
                }
            } else {
                if ($data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.transport')) . '(%)</td>'
                    );
                    $total_harga_discount_transport = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_harga_discount_transport =
                            $total_harga_discount_transport +
                            $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_transport) . "</td></tr>"
                    );
                }
                //Diskon PERDIEM
                if ($data->total_discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower')) . '(%)</td>'
                    );
                    $total_harga_discount_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_harga_discount_perdiem =
                            $total_harga_discount_perdiem +
                            $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_perdiem) . "</td></tr>"
                    );
                }
                //Diskon Perdiem 24 Jam
                if ($data->discount_perdiem_24jam != null && $data->total_discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower24')) . '(%)</td>'
                    );
                    $total_harga_discount__perdiem24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_harga_discount__perdiem24 =
                            $total_harga_discount__perdiem24 +
                            $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount__perdiem24) . "</td></tr>"
                    );
                }
            }

            //Diskon GABUNGAN
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.operational')) . '(%)</td>'
                );
                $total_harga_discount_gabungan = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_gabungan) . "</td>"
                    );
                    $total_harga_discount_gabungan =
                        $total_harga_discount_gabungan +
                        $value->total_discount_gabungan;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_gabungan) . "</td></tr>"
                );
            }

            // //Diskon CONSULTANT
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.consultant')) . '(%)</td>'
                );
                $total_harga_discount_consultant = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_consultant) . "</td>"
                    );
                    $total_harga_discount_consultant =
                        $total_harga_discount_consultant +
                        $value->total_discount_consultant;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_consultant) . "</td></tr>"
                );
            }

            //Diskon GROUP
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.group')) . '(%)</td>'
                );
                $total_harga_discount_group = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_group) . "</td>"
                    );
                    $total_harga_discount_group =
                        $total_harga_discount_group +
                        $value->total_discount_group;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_group) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT PERSEN
            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.percent')) . '(%)</td>'
                );
                $total_harga_discount_cash_per = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_cash_discount_persen) . "</td>"
                    );
                    $total_harga_discount_cash_per =
                        $total_harga_discount_cash_per +
                        $value->total_cash_discount_persen;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_cash_per) . "</td></tr>"
                );
            }

            // DISCOUNT CASH DISCOUNT
            if ($data->total_cash_discount > 0) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.cash')) . '</td>'
                );
                $total_harga_discount_cash = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->cash_discount) . "</td>"
                    );
                    $total_harga_discount_cash =
                        $total_harga_discount_cash + $value->cash_discount;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_discount_cash) . "</td></tr>"
                );
            }

            // CUSTOM DISCOUNT
            if ($data->total_custom_discount > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.custom')) . '</td>'
                );
                $total_custom_discount = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_custom_discount) . "</td>"
                    );
                    $total_custom_discount += $value->total_custom_discount;

                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_custom_discount) . "</td></tr>"
                );
            }

            // TOTAL HARGA SETELAH DISCOUNT
            if ($data->total_dpp != $data->grand_total) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;"><b>' . strtoupper(__('QTC.total.price_after_discount')) . '</b></td>'
                );
                $total_harga_setelah_discount = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->total_dpp) . "</b></td>"
                    );
                    $total_harga_setelah_discount += $value->total_dpp;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_harga_setelah_discount) . "</b></td></tr>"
                );
            }

            // PPN
            if ($data->total_ppn > 0 && $data->total_ppn != null) {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.tax.vat')) . $detail[0]->ppn . '"%</td>'
                );
                $total_harga_ppn = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_ppn) . "</td>"
                    );
                    $total_harga_ppn = $total_harga_ppn + $value->total_ppn;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_ppn) . "</td></tr>"
                );
            }

            // PPH
            if ($value->total_pph != "" && $value->total_pph != "0.00") {
                $pdf->WriteHTML(
                    '<tr><td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.tax.income')) . ' (' . $detail[0]->pph . "%)</td>"
                );
                $total_harga_pph = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_pph) . "</td>"
                    );
                    $total_harga_pph = $total_harga_pph + $value->total_pph;
                }
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_harga_pph) . "</td></tr>"
                );
            }

            // BIAYA DI LUAR PAJAK
            if ($data->total_biaya_di_luar_pajak > 0) {
                $pdf->WriteHTML(
                    '<tr>
                    <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.table.item.expenses.non_taxable')) . '</td>'
                );
                $total_biaya_di_luar_pajak = 0;
                foreach ($detail as $key => $value) {
                    $pdf->WriteHTML(
                        '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_biaya_di_luar_pajak) . "</td>"
                    );
                    $total_biaya_di_luar_pajak += $value->total_biaya_di_luar_pajak;

                }
                $pdf->WriteHTML(
                    ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_biaya_di_luar_pajak) . "</td></tr>"
                );
            }

            // START DATA DISKON DI LUAR PAJAK
            if (!is_null($diluar_pajak)) {
                // DISKON TRANSPORTASI
                if ($diluar_pajak->transportasi == 'true' && $data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.transport')) . '(%)</td>'
                    );
                    $total_diskon_transpotasi = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_transport) . "</td>"
                        );
                        $total_diskon_transpotasi += $value->total_discount_transport;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_transpotasi) . "</td></tr>"
                    );
                }

                // DISKON PERDIEM
                if ($diluar_pajak->perdiem == 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower')) . '(%)</td>'
                    );
                    $total_diskon_perdiem = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem) . "</td>"
                        );
                        $total_diskon_perdiem += $value->total_discount_perdiem;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_perdiem) . "</td></tr>"
                    );
                }

                // DISKON PERDIEM 24 JAM
                if ($diluar_pajak->perdiem24jam == 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                        <td style="text-align:center;font-size: 8px;">' . strtoupper(__('QTC.summary.discount.manpower24')) . '(%)</td>'
                    );
                    $total_diskon_perdiem_24 = 0;
                    foreach ($detail as $key => $value) {
                        $pdf->WriteHTML(
                            '<td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($value->total_discount_perdiem_24jam) . "</td>"
                        );
                        $total_diskon_perdiem_24 += $value->total_discount_perdiem_24jam;
                    }
                    $pdf->WriteHTML(
                        ' <td style="font-size: 8px; text-align:right; padding: 5px;">' . self::rupiah($total_diskon_perdiem_24) . "</td></tr>"
                    );
                }
            }

            // END DATA DISKON

            // TOTAL BIAYA AKHIR
            $pdf->WriteHTML(
                '<tr><td style="text-align:center;font-size: 8px;"><b>' . strtoupper(__('QTC.total.grand')) . '</b></td>'
            );
            $total_biaya_akhir = 0;
            foreach ($detail as $key => $value) {
                $pdf->WriteHTML(
                    '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($value->biaya_akhir) . "</b></td>"
                );
                $total_biaya_akhir =
                    $total_biaya_akhir + $value->biaya_akhir;
            }
            $pdf->WriteHTML(
                '<td style="font-size: 8px; text-align:right; padding: 5px;"><b>' . self::rupiah($total_biaya_akhir) . "</b></td></tr>"
            );

            $pdf->WriteHTML(
                '</tbody></table><table width="100%" style="margin-top:10px;"><tr>
                        <td width="40%"></td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>Tangerang, ' . self::tanggal_indonesia($data->tanggal_penawaran) . '</span>
                    <br>
                    <span>
                        <b>PT INTI SURYA LABORATORIUM</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>'
            );
            $pdf->WriteHTML(
                ' <td style="font-size: 10px;text-align:center;">
                    <span>' . __('QTC.approval.approving') . ',</span>
                    <br>
                    <span>
                        <b>' . $data->nama_perusahaan . '</b>
                    </span>
                    <br>
                    <br>
                    <br>
                </td>
                </tr>'
            );

            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                " <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>"
            );
            $pdf->WriteHTML(
                ' <tr>
                    <td></td>
                    <td style="font-size: 10px;text-align:center;">' . __('QTC.approval.name') . '&nbsp;&nbsp;&nbsp;(..............................................)</td>
                    <td style="font-size: 10px;text-align:center;">' . __('QTC.approval.name') . '&nbsp;&nbsp;&nbsp;(..............................................)</td>
                </tr>'
            );
            $pdf->WriteHTML(
                ' <tr>
                    <td></td>
                    <td style="font-size: 10px;text-align:center;">' . __('QTC.approval.position') . ' (..............................................)</td>
                    <td style="font-size: 10px;text-align:center;">' . __('QTC.approval.position') . ' (..............................................)</td>
                </tr>'
            );
            $pdf->WriteHTML("</table>");
            // Output a PDF file directly to the browser
            if ($lang == "en") {
                $filePath = public_path('quotation/en' . $fileName);
            } else {
                $filePath = public_path('quotation/' . $fileName);
            }
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                401
            );
        }
    }

    protected static function tanggal_indonesia($tanggal, $mode = null)
    {
        $carbonDate = Carbon::parse($tanggal);

        if ($mode == "period") {
            return $carbonDate->translatedFormat('F Y');
        } else {
            return $carbonDate->translatedFormat('d F Y');
        }
    }


    protected static function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }
}
