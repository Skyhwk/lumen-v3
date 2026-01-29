<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use App\Models\JobTask;
use Illuminate\Support\Facades\DB;
use Mpdf;
use App\Services\TranslatorService as Translator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class RenderNonKontrak
{
    public function renderHeader($id, $lang = 'id')
    {
        DB::beginTransaction();
        try {
            // app()->setLocale($lang);
            // Carbon::setLocale($lang);
            $lang = 'id';

            $update = QuotationNonKontrak::where('id', $id)->first();
            $filename = self::generate($id, $lang);
            if ($update && $filename) {
                $update->filename = $filename;
                $update->save();

                JobTask::insert([
                    'job' => 'RenderPdfPenawaran',
                    'status' => 'success',
                    'no_document' => $update->no_document,
                    'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
            }
            DB::commit();
            return true;
        } catch (\Exception $th) {
            DB::rollBack();
            JobTask::insert([
                'job' => 'RenderPdfPenawaran',
                'status' => 'failed',
                'no_document' => $update->no_document,
                'timestamp' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            Log::error(['RenderNonKontrak: ' . $th->getMessage() . ' - ' . $th->getFile() . ' - ' . $th->getLine()]);
            return false;
        }
    }

    private function generate($id, $lang)
    {
        try {
            $data = QuotationNonKontrak::with('cabang', 'sales')
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

            if ($data->status_sampling == 'S24') {
                $sampling = strtoupper(__('QT.status_sampling.S24'));
            } else if ($data->status_sampling == 'SD') {
                $sampling = strtoupper(__('QT.status_sampling.SD'));
            } else if ($data->status_sampling == 'RS') {
                $sampling = strtoupper(__('QT.status_sampling.RS'));
            } else if ($data->status_sampling == 'SP') {
                $sampling = strtoupper(__('QT.status_sampling.SP'));
            } else {
                $sampling = strtoupper(__('QT.status_sampling.S'));
            }

            $pdf = new Mpdf($mpdfConfig);

            $pdf->charset_in = 'utf-8';
            $pdf->SetProtection(array(
                'print'
            ), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(
                65,
                60
            ));
            $pdf->showWatermarkImage = true;
            // $pdf->SetWatermarkText('CONFIDENTIAL');
            // $pdf->showWatermarkText = true;

            $qr_img = '';
            $qr = DB::table('qr_documents')->where(['id_document' => $id, 'type_document' => 'quotation_non_kontrak'])
                // ->whereJsonContains('data->no_document', $data->no_document)
                ->first();

            $qr_data = $qr && $qr->data ? json_decode($qr->data, true) : null;

            if ($qr && ($qr_data['no_document'] == $data->no_document))
                $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';

            $footer = array(
                'odd' => array(
                    'C' => array(
                        'content' => __('QT.footer.center_content', ['page' => '{PAGENO}', 'total_pages' => '{nbpg}']),
                        'font-size' => 6,
                        'font-style' => 'I',
                        'font-family' => 'serif',
                        'color' => '#606060'
                    ),
                    'R' => array(
                        'content' => __('QT.footer.right_content') . ' <br> {DATE YmdGi}',
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
            $no_pic_sampling = '';
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
            if ($data->no_pic_order != '')
                $no_pic_order = ' -' . $data->no_pic_order;
            if ($data->no_tlp_pic_sampling != '')
                $no_pic_sampling = ' -' . $data->no_tlp_pic_sampling;

            $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';

            $pdf->SetHTMLHeader('
                    <table class="tabel">
                        <tr class="tr_top">
                            <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                                    src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                            </td>
                            <td style="width: 33.33%; text-align: center;">
                                <h5 style="text-align:center; font-size:14px;"><b><u>' . strtoupper(__('QT.header.quotation')) . '</u></b></h5>
                                <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . '
                                </p>
                            </td>
                            <td style="text-align: right;">
                                <p style="font-size: 9px; text-align:right;"><b>PT INTI SURYA
                                        LABORATORIUM</b><br><span
                                        style="white-space: pre-wrap; word-wrap: break-word;">' . $data
                    ->cabang->alamat_cabang . '</span><br><span>T : ' . $data
                    ->cabang->tlp_cabang . ' - sales@intilab.com</span><br>www.intilab.com
                                </p>
                            </td>
                        </tr>
                    </table>
                    <table class="head2" width="100%">
                        <tr>
                            <td colspan="2"><p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia($data->tanggal_penawaran) . '</p></td>
                            <td style="vertical-align: top; text-align:right;"><span
                            style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-bottom:20px;"
                            id="status_sampling">' . $sampling . '</span></td>
                        </tr>
                        <tr>
                            <td colspan="2" width="80%"><h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . preg_replace('/&AMP;+/', '&', $perusahaan) . '</h6></td>
                            <td style="vertical-align: top; text-align:right;"></td>
                        </tr>
                        <tr>
                            <td style="width:35%;vertical-align:top;"><p style="font-size: 10px;"><u>' . __('QT.header.office') . ' :</u><br><span
                            id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span><br><span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span><br><span
                            id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . $no_pic_order . '</span><br><span id="email_pic_order">' . $data->email_pic_order . '</span></p></td>
                            <td style="width: 30%; text-align: center;"></td>
                            <td style="text-align: left;vertical-align:top;"><p style="font-size: 10px;"><u>' . __('QT.header.sampling') . ' :</u><br><span
                            id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span><br><span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span><br><span
                            id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . $no_pic_sampling . '</span><br><span id="email_pic_sampling">' . $data->email_pic_sampling . '</span></p></td>
                        </tr>
                    </table>
                ');

            $getBody = self::renderBody($pdf, $data, $fileName, $lang);

            return $getBody;
        } catch (\Throwable $th) {
            Log::error(['RenderNonKontrakHeader with id: ' . $id . ' : ' . $th->getMessage() . ' - ' . $th->getFile() . ' - ' . $th->getLine()]);
            return false;
        }
    }

    public function renderBody($pdf, $data, $fileName, $lang)
    {
        try {
            /* set locale dari jadwal */
            app()->setLocale($lang);
            Carbon::setLocale($lang);

            $pdf->WriteHTML('<table class="table table-bordered" style="font-size: 8px;">
                    <thead class="text-center">
                        <tr>
                            <th width="2%" style="padding: 5px !important;">' . strtoupper(__('QT.table.header.no')) . '</th>
                            <th width="62%">' . strtoupper(__('QT.table.header.description')) . '</th>
                            <th width="12%">' . __('QT.table.header.quantity') . '</th>
                            <th width="12%">' . strtoupper(__('QT.table.header.unit_price')) . '</th>
                            <th width="12%">' . strtoupper(__('QT.table.header.total_price')) . '</th>
                        </tr>
                    </thead>
                <tbody>');
            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {
                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);
                $kategori2Value = isset($kategori2[1]) ? $kategori2[1] : '';
                $penamaan_titik = "";
                if ($a->penamaan_titik != null && $a->penamaan_titik != "") {
                    if (is_array($a->penamaan_titik)) {
                        $filtered_array = array_filter($a->penamaan_titik, function ($item) {
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
                        $penamaan_titik = "(" . $a->penamaan_titik . ")";
                    }
                } else {
                    $penamaan_titik = "";
                }

                /* Hidupin untuk tampilkan penamaan titik
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px; padding:5px">
                        <b style="font-size: 13px;">' . $kategori2Value . " " . $penamaan_titik . "</b>
                        <hr>"
                );*/

                $pdf->WriteHTML(
                    ' <tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px; padding:5px">
                        <b style="font-size: 13px;">' . $kategori2Value . "</b>
                        <hr>"
                );

                if (!is_null($a->regulasi) || $a->regulasi != "") {
                    foreach ($a->regulasi as $k => $v) {
                        $reg__ = '';

                        if ($v != '') {
                            $regulasi = array_slice(explode("-", $v), 1);
                            $reg__ = implode("-", $regulasi);
                        }
                        if ($k == 0) {
                            $pdf->WriteHTML(
                                ' <u style="font-size: 13px;">' . $reg__ . "</u>"
                            );
                        } else {
                            $pdf->WriteHTML(
                                ' <br>
                                <u style="font-size: 13px;">' . $reg__ . "</u>"
                            );
                        }
                    }
                }

                $akreditasi = [];
                $non_akre = [];
                foreach ($a->parameter as $keys => $values) {
                    $d = Parameter::where("id", explode(";", $values)[0])
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
                            '
                            <br>
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
                            '
                            <br>
                            <hr>
                            <span style="font-size: 13px; float:left; display: inline; text-align:left;">' . $d->nama_regulasi . "</span>"
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
                        " - " . __('QT.table.item.volume') . " : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }

                /* Hidupin untuk tampilkan akreditasi
                $pdf->WriteHTML(
                    '<br>
                    <hr>' . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">' . __('QT.table.item.total_parameter') . ' : ' . count($a->parameter) . $volume . ' - KAN (P) : ' . count($akreditasi) . ' (' . count($non_akre) . ')' . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_total) . '</td>
                    </tr>'
                );*/

                $pdf->WriteHTML(
                    '<br>
                    <hr>' . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">' . __('QT.table.item.total_parameter') . ' : ' . count($a->parameter) . $volume . '</span>
                    </b>
                    </td>
                    <td style="vertical-align: middle;text-align:center;font-size: 13px;">' . $a->jumlah_titik . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_satuan) . '</td>
                    <td style="vertical-align: middle;text-align:right;font-size: 13px;">' . self::rupiah($a->harga_total) . '</td>
                    </tr>'
                );

                $i++;
            }
            $wilayah = explode("-", $data->wilayah, 2);
            if ($data->transportasi > 0 && $data->harga_transportasi_total != null && !is_null($data->transportasi)) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QT.table.item.transport') . ' : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi * $data->transportasi) . '</td>
                    </tr>'
                );
            }
            $perdiem_24 = "";
            $total_perdiem = 0;

            if (
                $data->jumlah_orang_24jam > 0 &&
                $data->jumlah_orang_24jam != "" && $data->harga_24jam_personil_total != null
                && !is_null($data->harga_24jam_personil_total)
            ) {
                $perdiem_24 = __('QT.table.item.manpower24');
                $total_perdiem += $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                    $i = $i + 1;
                }
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">' . __('QT.table.item.manpower') . $perdiem_24 . '</td>
                        <td style="font-size: 13px; text-align:center;"></td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_perdiem_personil_total + $total_perdiem) . '</td>
                    </tr>'
                );
            }
            $biaya_lain = json_decode($data->biaya_lain);
            if ($biaya_lain != null) {
                $u = '';
                foreach ($biaya_lain as $k => $v) {
                    if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                        $i = $i + 1;
                    } else {
                        if ($u == $i) {
                            $i = $i + 1;
                        } else {
                            if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                                $i = $i + 1;
                            }
                        }
                    }

                    if ($lang != 'id') {
                        $v->deskripsi = str_replace('Perdiem', 'Manpower', $v->deskripsi);
                        $tranlator = new Translator();
                        $v->deskripsi = $tranlator->translate($v->deskripsi, 'id', $lang);
                    }

                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">' . __('QT.table.item.expenses.cost') . ' : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($v->total_biaya) . '</td>
                        </tr>'
                    );
                    $u = $i;
                }
            }
            $biaya_preparasi = json_decode($data->biaya_preparasi_padatan);
            if ($biaya_preparasi != null) {
                // $i = $i + 1;
                foreach ($biaya_preparasi as $k => $v) {
                    if ($lang != 'id') {
                        $v->deskripsi = str_replace('Perdiem', 'Manpower', $v->deskripsi);
                        $tranlator = new Translator();
                        $v->deskripsi = $tranlator->translate($v->deskripsi, 'id', $lang);
                    }

                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            <td style="font-size: 13px;padding: 5px;">' . __('QT.table.item.expenses.cost') . ' : ' . $v->deskripsi . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;"></td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($v->harga) . '</td>
                            <td style="font-size: 13px; text-align:right;padding: 5px;">' . self::rupiah($v->harga) . '</td>
                        </tr>'
                    );
                }
            }
            $pdf->WriteHTML("</tbody></table>");
            $pdf->WriteHTML(
                '<table width="100%" style="line-height: 2;">
                    <tr>
                        <td style="font-size: 10px;vertical-align: top;" width="64%">
                            <u>
                                <b>' . __('QT.terms_conditions.payment.title') . '</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            if ($syarat_ketentuan != null) {
                // if ($syarat_ketentuan->pembayaran != null) { Update by Afryan at 2025-02-04 to handle pembayaran
                if (isset($syarat_ketentuan->pembayaran) && $syarat_ketentuan->pembayaran != null) {
                    if ($data->cash_discount_persen != null || $data->cash_discount_persen > 0) {
                        $pdf->WriteHTML(
                            '<br><span style="font-size: 10px !important;">' . __('QT.terms_conditions.payment.cash_discount') . '</span>'
                        );
                    }
                    foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                        if (preg_match('/Pembayaran (\d+) Hari setelah Laporan Hasil Pengujian dan Invoice diterima lengkap oleh pihak pelanggan\./', $v, $matches)) {
                            $days = $matches[1];
                            $v = __('QT.terms_conditions.payment.1', ['days' => $days]);
                        } else if (preg_match('/Pembayaran (\d+)% lunas sebelum sampling dilakukan\./', $v, $matches)) {
                            $percent = $matches[1];
                            $v = __('QT.terms_conditions.payment.2', ['percent' => $percent]);
                        } else if (preg_match('/Masa berlaku penawaran (\d+) hari\./', $v, $matches)) {
                            $days = $matches[1];
                            $v = __('QT.terms_conditions.payment.3', ['days' => $days]);
                        } else if (preg_match('/^Pembayaran Lunas saat sampling dilakukan oleh pihak pelanggan\.$/i', $v)) {
                            $v = __('QT.terms_conditions.payment.4');
                        } else if (preg_match('/Pembayaran ([\d.,]+) Down Payment \(DP\), Pelunasan saat (.+)/i', $v, $matches)) {
                            $amount = $matches[1];
                            $text = $matches[2];
                            if ($lang != 'id') {
                                $tranlator = new Translator();
                                $text = $tranlator->translate($text, 'id', $lang);
                            }
                            $v = __('QT.terms_conditions.payment.5', ['amount' => $amount, 'text' => $text]);
                        } else if (preg_match('/Pembayaran I sebesar ([\d.,]+), Pelunasan saat (.+)/i', $v, $matches)) {
                            $amount = $matches[1];
                            $text = $matches[2];
                            if ($lang != 'id') {
                                $tranlator = new Translator();
                                $text = $tranlator->translate($text, 'id', $lang);
                            }
                            $v = __('QT.terms_conditions.payment.6', ['amount' => $amount, 'text' => $text]);
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

                            $v = __('QT.terms_conditions.payment.7', [
                                'count' => $jumlahTahap,
                                'amount1' => $tahap1,
                                'amount2' => $tahap2,
                                'amount3' => $tahap3
                            ]);
                        } else if (preg_match('/^Pembayaran (\d+)% DP, Pelunasan saat draft Laporan Hasil Pengujian diterima pelanggan\./', $v, $matches)) {
                            $percent = $matches[1];
                            $v = __('QT.terms_conditions.payment.8', ['percent' => $percent]);
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
                                <b>' . strtoupper(__('QT.total.sub')) . '</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->grand_total) . '</td>
                        </tr>'
            );
            if ($data->total_discount_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.water') . $data->discount_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_non_air > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.non_water') . $data->discount_non_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_non_air) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_udara > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.air') . $data->discount_udara . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_udara) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_emisi > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.emission') . $data->discount_emisi . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_emisi) . '</td>
                    </tr> '
                );
            }
            $diluar_pajak = json_decode($data->diluar_pajak);
            if (!is_null($diluar_pajak)) {
                if ($data->total_discount_transport > 0 && $diluar_pajak->transportasi != 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.transport') . $data->discount_transport . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr>'
                    );
                }
                if ($data->total_discount_perdiem > 0 && $diluar_pajak->perdiem != 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.manpower') . $data->discount_perdiem . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr>'
                    );
                }
                if ($data->total_discount_perdiem_24jam > 0 && $diluar_pajak->perdiem24jam != 'true') {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.manpower24') . $data->discount_perdiem_24jam . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr>'
                    );
                }
            } else {
                if ($data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.transport') . $data->discount_transport . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr>'
                    );
                }
                if ($data->total_discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.manpower') . $data->discount_perdiem . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr>'
                    );
                }
                if ($data->total_discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.manpower24') . $data->discount_perdiem_24jam . ' %</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr>'
                    );
                }
            }
            if ($data->total_discount_gabungan > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.operational') . trim(str_replace('%', '', $data->discount_gabungan)) . '%</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_consultant > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.consultant') . $data->discount_consultant . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->total_discount_group > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.group') . $data->discount_group . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }
            if ($data->total_cash_discount_persen > 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.percent') . $data->cash_discount_persen . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->cash_discount != 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . __('QT.discount.contract.cash') . '</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->cash_discount) . '</td>
                    </tr> '
                );
            }
            $disc_custom = json_decode($data->custom_discount);
            if ($disc_custom != null) {
                foreach ($disc_custom as $k => $v) {
                    if ($lang != 'id') {
                        $v->deskripsi = str_replace('Perdiem', 'Manpower', $v->deskripsi);
                        $tranlator = new Translator();
                        $v->deskripsi = $tranlator->translate($v->deskripsi, 'id', $lang);
                    }

                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . $v->deskripsi . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->discount) . '</td>
                        </tr> '
                    );
                }
            }
            $disc_promo = json_decode($data->discount_promo);
            if ($disc_promo != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . $disc_promo->deskripsi_promo_discount .' '. $disc_promo->jumlah_promo_discount .'</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_promo) . '</td>
                    </tr> '
                );
            }
            if ($data->total_dpp != $data->grand_total && $data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>' . strtoupper(__('QT.total.after_discount')) . '</b>
                        </td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            if ($data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . strtoupper(__('QT.tax.vat')) . $data->ppn . '%</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_ppn) . '</td>
                    </tr> '
                );
            }


            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">' . strtoupper(__('QT.tax.income')) . $data->pph . '%</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_pph) . '</td>
                    </tr> '
                );
            }

            $v = json_decode($data->biaya_di_luar_pajak);
            if ($v != null) {
                if ($v->body != null || $v->select) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">
                                <b>' . strtoupper(__('QT.total.after_tax')) . '</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->piutang) . '</td>
                        </tr> '
                    );
                }
                if ($v->select != null) {
                    foreach ($v->select as $k => $c) {
                        if ($c->harga != null || $c->harga != 0) {
                            if ($lang != 'id') {
                                $c->deskripsi = str_replace('Perdiem', 'Manpower', $c->deskripsi);
                                $tranlator = new Translator();
                                $c->deskripsi = $tranlator->translate($c->deskripsi, 'id', $lang);
                            }

                            $pdf->WriteHTML(
                                '
                                    <tr>
                                        <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                        <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                                    </tr>
                                '
                            );
                        }
                    }
                }
                if ($v->body != null) {
                    foreach ($v->body as $k => $c) {
                        if ($c->harga != null || $c->harga != 0) {
                            if ($lang != 'id') {
                                $c->deskripsi = str_replace('Perdiem', 'Manpower', $c->deskripsi);
                                $tranlator = new Translator();
                                $c->deskripsi = $tranlator->translate($c->deskripsi, 'id', $lang);
                            }

                            $pdf->WriteHTML(
                                ' <tr>
                                    <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                    <td style="text-align:right;padding:5px;"> ' . self::rupiah(\str_replace('.', '', $c->harga)) . '</td>
                                </tr> '
                            );
                        }
                    }
                }
            }

            if (!is_null($diluar_pajak)) {
                if ($diluar_pajak->transportasi == 'true' && $data->total_discount_transport > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.non_taxable.transport') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                        </tr> '
                    );
                }

                if ($diluar_pajak->perdiem == 'true' && $data->total_discount_perdiem > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.non_taxable.manpower') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                        </tr> '
                    );
                }

                if ($diluar_pajak->perdiem24jam == 'true' && $data->total_discount_perdiem_24jam > 0) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . __('QT.discount.non_taxable.manpower24') . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                        </tr> '
                    );
                }
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>' . strtoupper(__('QT.total.price')) . '</b>
                    </td>
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
                                <b>' . __('QT.terms_conditions.additional.title') . '</b>
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
            if ($syarat_ketentuan != null) {
                if ($syarat_ketentuan->umum != null) {
                    /* Hidupin untuk tampilkan akreditasi
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>' . __('QT.terms_conditions.general.title') . '</b>
                                </u>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 10px;">' . __('QT.terms_conditions.general.accreditation') . '</td>
                        </tr>'
                    );*/

                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>' . __('QT.terms_conditions.general.title') . '</b>
                                </u>
                            </td>
                        </tr>'
                    );

                    foreach ($syarat_ketentuan->umum as $k => $v) {
                        if ($v != "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan") {
                            if (preg_match('/^Untuk kategori Udara, <b>harga sudah termasuk<\/b> parameter <b>Suhu - Kecepatan Angin - Arah Angin - Kelembaban - Cuaca\.<\/b>$/', $v)) {
                                $v = __('QT.terms_conditions.general.1');
                            } else if (preg_match('/^Sumber listrik disediakan oleh pihak pelanggan\.$/i', $v)) {
                                $v = __('QT.terms_conditions.general.2');
                            } else if (preg_match('/^Harga di atas untuk jumlah titik sampling yang tertera dan dapat berubah disesuaikan dengan kondisi lapangan dan permintaan pelanggan\.$/i', $v)) {
                                $v = __('QT.terms_conditions.general.3');
                            } else if (preg_match('/^Pembatalan atau penjadwalan ulang oleh pihak pelanggan akan dikenakan biaya transportasi dan\/atau perdiem\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.4');
                            } else if (preg_match('/^Pekerjaan akan dilaksanakan setelah pihak kami menerima konfirmasi berupa dokumen PO \/ SPK dari pihak pelanggan\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.5');
                            } else if (preg_match('/^Bagi perusahaan yang tidak menerbitkan PO \/ SPK, dapat menandatangani penawaran harga sebagai bentuk persetujuan pelaksanaan pekerjaan\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.6');
                            } else if (preg_match('/^Laporan Hasil Pengujian akan dikeluarkan dalam jangka waktu 10 hari kerja, terhitung sejak tanggal sampel diterima di laboratorium\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.7');
                            } else if (preg_match('/^Optimal perhari 1 \(satu\) tim sampling \(2 orang\) bisa mengerjakan 6 titik udara \(Ambient \/ Lingkungan Kerja\)\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.8');
                            } else if (preg_match('/^Jangka waktu pembuatan dokumen dikerjakan selama 2 - 3 bulan, dengan kewajiban pelanggan melengkapi dokumen sebelum sampling dilakukan\.?$/i', $v)) {
                                $v = __('QT.terms_conditions.general.9');
                            } else if (preg_match('/^Biaya sudah termasuk (.+)$/i', $v, $matches)) {
                                $text = $matches[1];
                                if ($lang != 'id') {
                                    $tranlator = new Translator();
                                    $text = $tranlator->translate($text, 'id', $lang);
                                }
                                $v = __('QT.terms_conditions.general.10', [
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
                            <u>' . __('QT.approval.proof') . '</u>
                        </b>
                    </td>
                </tr>'
            );
            $add = "-";
            $upd = "-";
            $no_telp = "";
            $app = "-";
            $add = $data->sales->nama_lengkap;
            $no_telp = " (" . $data->sales->no_telpon . ")";
            $upd = $data->updated_by;
            if ($data->is_approved == 0) {
                $st = "NOT APPROVED";
            } else {
                $st = "APPROVED";
            }

            $sp = SamplingPlan::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                ->where('status', 1)
                ->first();

            if (!empty($sp)) {
                $jadwal = Jadwal::where('is_active', true)
                    ->where('id_sampling', $sp->id)
                    ->select('tanggal')
                    ->first();
                if (!empty($jadwal)) {
                    $pdf->WriteHTML(
                        ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">Tanggal Sampling : ' . self::tanggal_indonesia($jadwal->tanggal) . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                    );
                } else {
                    $pdf->WriteHTML(
                        ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                    );
                }
            } else {
                $pdf->WriteHTML(
                    ' <tr>
                        <td>
                            <span style="font-size:11px;">Administrasi : ' . $upd . '</span>
                            <br>
                            <span style="font-size:11px;">Status : ' . $st . '</span>
                            <br>
                            <span style="font-size:11px;">PIC Sales : ' . $add . $no_telp . '</span>
                            <br>
                        </td>
                        <td style="font-size: 10px;text-align:center;">
                            <span>Menyetujui,</span>
                            <br>
                            <br>
                            <br>
                            <span>Nama (..............................................)</span>
                            <br>
                            <span>Jabatan (..............................................)</span>
                        </td> '
                );
            }
            $pdf->WriteHTML("</tr></table>");

            $dir = public_path('quotation/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            if ($lang == "en") {
                $filePath = public_path('quotation/en' . $fileName);
            } else {
                $filePath = public_path('quotation/' . $fileName);
            }

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            Log::error(['RenderNonKontrakBody: ' . $e->getMessage() . ' - ' . $e->getFile() . ' - ' . $e->getLine()]);
            return response()->json(
                [
                    "message" => $e->getMessage(),
                    "line" => $e->getLine(),
                    "file" => $e->getFile(),
                ],
                400
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

    protected function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }
}
