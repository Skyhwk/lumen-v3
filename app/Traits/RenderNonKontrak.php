<?php

namespace App\Traits;

use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use Illuminate\Support\Facades\DB;
use Mpdf;

trait RenderNonKontrak
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function renderDataQuotation($id)
    {
        DB::beginTransaction();
        try {
            $filename = $this->renderHeader($id);

            $update = QuotationNonKontrak::where('id', $id)->first();
            if ($update) {
                $update->filename = $filename;
                $update->save();
            }
            DB::commit();
            return true;
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine()
            ], 401);
        }
    }

    static function renderHeader($id)
    {
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

        if ($data->status_sampling == 'S24') {
            $sampling = 'SAMPLING 24 JAM';
        } else if ($data->status_sampling == 'SD') {
            $sampling = 'SAMPLE DIANTAR';
        } else if ($data->status_sampling == 'RS') {
            $sampling = 'RE-SAMPLE';
        } else if ($data->status_sampling == 'SP') {
            $sampling = 'SAMPLE PICKUP';
        } else {
            $sampling = 'SAMPLING';
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
        $footer = array(
            'odd' => array(
                'C' => array(
                    'content' => 'Hal {PAGENO} dari {nbpg}',
                    'font-size' => 6,
                    'font-style' => 'I',
                    'font-family' => 'serif',
                    'color' => '#606060'
                ),
                'R' => array(
                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                    'font-size' => 5,
                    'font-style' => 'I',
                    // 'font-style' => 'B',
                    'font-family' => 'serif',
                    'color' => '#000000'
                ),
                'L' => array(
                    'content' => '',
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
        if ($data->no_pic_order != '')
            $no_pic_order = ' -' . $data->no_pic_order;
        if ($data->no_tlp_pic_sampling != '')
            $no_pic_sampling = ' -' . $data->no_tlp_pic_sampling;

        $fileName = preg_replace('/\\//', '-', $data->no_document) . '.pdf';

        $pdf->SetHTMLHeader(
            ' <table class="tabel">
              <tr class="tr_top">
                <td class="text-left text-wrap" style="width: 33.33%;">
                  <img class="img_0" src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                </td>
                <td style="width: 33.33%; text-align: center;">
                  <h5 style="text-align:center; font-size:14px;">
                    <b>
                      <u>QUOTATION</u>
                    </b>
                  </h5>
                  <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $data->no_document . ' </p>
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
                  <p style="font-size: 10px;line-height:1.5px;">Tangerang, ' . self::tanggal_indonesia(date('Y-m-d')) . '</p>
                </td>
                <td style="vertical-align: top; text-align:right;">
                  <span style="font-size:11px; font-weight: bold; border: 1px solid gray;margin-bottom:20px;" id="status_sampling">' . $sampling . '</span>
                </td>
              </tr>
              <tr>
                <td colspan="2" width="80%">
                  <h6 style="font-size:9pt; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . htmlspecialchars_decode($perusahaan) . '</h6>
                </td>
                <td style="vertical-align: top; text-align:right;"></td>
              </tr>
              <tr>
                <td style="width:35%;vertical-align:top;">
                  <p style="font-size: 10px;">
                    <u>Alamat Kantor :</u>
                    <br>
                    <span id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_kantor . '</span>
                    <br>
                    <span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span>
                    <br>
                    <span id="nama_pic_order">' . $data->nama_pic_order . $jab_pic_or . $no_pic_order . '</span>
                    <br>
                    <span id="email_pic_order">' . $data->email_pic_order . '</span>
                  </p>
                </td>
                <td style="width: 30%; text-align: center;"></td>
                <td style="text-align: left;vertical-align:top;">
                  <p style="font-size: 10px;">
                    <u>Alamat Sampling :</u>
                    <br>
                    <span id="alamat_sampling" style="white-space: pre-wrap; word-wrap: break-word;">' . $data->alamat_sampling . '</span>
                    <br>
                    <span id="no_tlp_pic">' . $data->no_tlp_pic_sampling . '</span>
                    <br>
                    <span id="nama_pic_sampling">' . $data->nama_pic_sampling . $jab_pic_samp . $no_pic_sampling . '</span>
                    <br>
                    <span id="email_pic_sampling">' . $data->email_pic_sampling . '</span>
                  </p>
                </td>
              </tr>
            </table> '
        );

        $getBody = self::renderBody($pdf, $data, $fileName);

        return $getBody;
    }

    protected function renderBody($pdf, $data, $fileName)
    {
        try {
            $pdf->WriteHTML('<table class="table table-bordered" style="font-size: 8px;">
                 <thead class="text-center">
                     <tr>
                         <th width="2%" style="padding: 5px !important;">NO</th>
                         <th width="62%">KETERANGAN PENGUJIAN</th>
                         <th width="12%">Qty</th>
                         <th width="12%">HARGA SATUAN</th>
                         <th width="12 %">TOTAL HARGA</th>
                     </tr>
                 </thead>
                 <tbody>');
            $i = 1;
            foreach (json_decode($data->data_pendukung_sampling) as $key => $a) {

                $kategori = explode("-", $a->kategori_1);
                $kategori2 = explode("-", $a->kategori_2);
                $penamaan_titik = "";
                $kategori2Value = isset($kategori2[1]) ? $kategori2[1] : '';
                if ($a->penamaan_titik != null || $a->penamaan_titik != "") {
                    $penamaan_titik = "(" . \htmlspecialchars_decode(implode(', ', $a->penamaan_titik)) . ")";
                }

                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td><td style="font-size: 13px; padding:5px"><b style="font-size: 13px;">' . $kategori2Value . " " . $penamaan_titik . "</b><hr>"
                );
                foreach ($a->regulasi as $k => $v) {
                    $reg__ = '';

                    if ($v != '') {
                        $regulasi = array_slice(explode("-", $v), 1);
                        $reg__ = implode("-", $regulasi);
                    }
                    if ($k == 0) {
                        $pdf->WriteHTML(
                            '<u style="font-size: 13px;">' .
                            $reg__ .
                            "</u>"
                        );
                    } else {
                        $pdf->WriteHTML(
                            '<br><u style="font-size: 13px;">' .
                            $reg__ .
                            "</u>"
                        );
                    }
                }

                foreach ($a->parameter as $keys => $values) {
                    $d = Parameter::where("id", explode(";", $values)[0])
                        ->where("is_active", true)
                        ->first();

                    if ($keys == 0) {
                        $pdf->WriteHTML('
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
                        " - Volume : " .
                        number_format($a->volume / 1000, 1) .
                        " L";
                }
                $pdf->WriteHTML(
                    '<br>
                    <hr>' . ' <b>
                        <span style="font-size: 13px; margin-top: 5px;">Total Parameter : ' . count($a->parameter) . $volume . '</span>
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
            if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Transportasi - Wilayah Sampling : ' . $wilayah[1] . '</td>
                        <td style="font-size: 13px; text-align:center;">' . $data->transportasi . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi) . '</td>
                        <td style="font-size: 13px; text-align:right; padding: 5px;">' . self::rupiah($data->harga_transportasi * $data->transportasi) . '</td>
                    </tr>'
                );
            }
            $perdiem_24 = "";
            $total_perdiem = 0;

            if (
                $data->{'24jam_jumlah_orang'} > 0 &&
                $data->{'24jam_jumlah_orang'} != "" && $data->harga_24jam_personil_total != null
            ) {
                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                $total_perdiem =
                    $total_perdiem + $data->{'harga_24jam_personil_total'};
            }
            if ($data->perdiem_jumlah_orang > 0 && $data->harga_perdiem_personil_total != null) {
                if ($data->transportasi > 0 && $data->harga_transportasi_total != null) {
                    $i = $i + 1;
                }
                $pdf->WriteHTML(
                    '<tr>
                        <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                        <td style="font-size: 13px;padding: 5px;">Perdiem ' . $perdiem_24 . '</td>
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
                        }
                    }
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
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
                    $pdf->WriteHTML(
                        '<tr>
                            <td style="vertical-align: middle; text-align:center;font-size: 13px;">' . $i++ . '</td>
                            <td style="font-size: 13px;padding: 5px;">Biaya : ' . $v->deskripsi . '</td>
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
                                <b>Syarat dan Ketentuan Pembayaran</b>
                            </u>'
            );

            $syarat_ketentuan = json_decode($data->syarat_ketentuan);
            if ($syarat_ketentuan != null) {
                if ($syarat_ketentuan->pembayaran != null) {
                    if ($data->cash_discount_persen != null) {
                        $pdf->WriteHTML(
                            '<br><span style="font-size: 10px !important;">- Cash Discount berlaku apabila pelunasan keseluruhan sebelum sampling.</span>'
                        );
                    }
                    foreach ($syarat_ketentuan->pembayaran as $k => $v) {
                        $pdf->WriteHTML(
                            '<br>
                            <span style="font-size: 10px !important;">- ' . $v . "</span>"
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
                                <b>SUB TOTAL</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->grand_total) . '</td>
                        </tr>'
            );
            if ($data->discount_air != null && $data->total_discount_air != "0.00") {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Air ' . $data->discount_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_air) . '</td>
                    </tr>'
                );
            }
            if (
                $data->discount_non_air != null &&
                $data->total_discount_non_air != "0.00"
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Non Air ' . $data->discount_non_air . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_non_air) . '</td>
                    </tr>'
                );
            }
            if (
                $data->discount_udara != null &&
                $data->total_discount_udara != "0.00"
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Udara ' . $data->discount_udara . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_udara) . '</td>
                    </tr>'
                );
            }
            if (
                $data->discount_emisi != null &&
                $data->total_discount_emisi != "0.00"
            ) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Emisi ' . $data->discount_emisi . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_emisi) . '</td>
                    </tr>'
                );
            }
            if ($data->discount_transport != null) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Transport ' . $data->discount_transport . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_transport) . '</td>
                    </tr>'
                );
            }
            if ($data->discount_perdiem != null) {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem ' . $data->discount_perdiem . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem) . '</td>
                    </tr>'
                );
            }
            if ($data->discount_perdiem_24jam != null && $data->discount_perdiem_24jam != '0') {
                $pdf->WriteHTML(
                    '<tr>
                        <td style="text-align:center;padding:5px;">Disc. Perdiem 24 Jam ' . $data->discount_perdiem_24jam . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_perdiem_24jam) . '</td>
                    </tr>'
                );
            }
            if ($data->discount_gabungan != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Gabungan ' . $data->discount_gabungan . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_gabungan) . '</td>
                    </tr> '
                );
            }
            if ($data->discount_consultant != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Consultant ' . $data->discount_consultant . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_consultant) . '</td>
                    </tr> '
                );
            }
            if ($data->discount_group != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Disc. Group ' . $data->discount_group . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_discount_group) . '</td>
                    </tr> '
                );
            }

            if ($data->cash_discount_persen != null) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc. ' . $data->cash_discount_persen . ' %</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_cash_discount_persen) . '</td>
                    </tr> '
                );
            }
            if ($data->cash_discount != 0) {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">Cash Disc.</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->cash_discount) . '</td>
                    </tr> '
                );
            }
            $disc_custom = json_decode($data->custom_discount);
            if ($disc_custom != null) {
                foreach ($disc_custom as $k => $v) {
                    $pdf->WriteHTML(
                        ' <tr>
                            <td style="text-align:center;padding:5px;">' . $v->deskripsi . '</td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($v->discount) . '</td>
                        </tr> '
                    );
                }
            }
            if ($data->total_dpp != $data->grand_total && $data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">
                            <b>TOTAL SETELAH DISKON</b>
                        </td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_dpp) . '</td>
                    </tr>'
                );
            }

            if ($data->total_ppn != null && $data->total_ppn != '0.00') {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPN 11%</td>
                        <td style="text-align:right;padding:5px;">' . self::rupiah($data->total_ppn) . '</td>
                    </tr> '
                );
            }


            if ($data->total_pph > 0 && $data->total_pph != "") {
                $pdf->WriteHTML(
                    ' <tr>
                        <td style="text-align:center;padding:5px;">PPH ' . $data->pph . '%</td>
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
                                <b>TOTAL SETELAH PAJAK</b>
                            </td>
                            <td style="text-align:right;padding:5px;">' . self::rupiah($data->piutang) . '</td>
                        </tr> '
                    );
                }
                if ($v->select != null) {
                    foreach ($v->select as $k => $c) {
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
                if ($v->body != null) {
                    foreach ($v->body as $k => $c) {
                        $pdf->WriteHTML(
                            ' <tr>
                                <td style="text-align:center;padding:5px;">' . $c->deskripsi . '</td>
                                <td style="text-align:right;padding:5px;">' . self::rupiah($c->harga) . '</td>
                            </tr> '
                        );
                    }
                }
            }

            $pdf->WriteHTML(
                ' <tr>
                    <td style="text-align:center;padding:5px;">
                        <b>TOTAL HARGA</b>
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
                                <b>Keterangan Lain / Tambahan</b>
                            </u>
                        </td>
                    </tr>'
                );
                foreach (json_decode($data->keterangan_tambahan) as $k => $v) {
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
                    $pdf->WriteHTML(
                        ' <tr style="font-size: 10px !important;">
                            <td style="font-size: 10px; !important">
                                <u>
                                    <b>Syarat dan Ketentuan Umum</b>
                                </u>
                            </td>
                        </tr>'
                    );
                    foreach ($syarat_ketentuan->umum as $k => $v) {
                        if (
                            $v !=
                            "Biaya Tiket perjalanan, Transportasi Darat dan Penginapan ditanggung oleh pihak pelanggan"
                        ) {
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
                            <u>Sebagai tanda persetujuan, agar dapat menandatangani serta mengirimkan kembali kepada pihak kami melalui email : sales@intilab.com</u>
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
            if ($data->approve == 0) {
                $st = "NOT APPROVED";
            } else {
                $st = "APPROVED";
            }

            $sp = SamplingPlan::where('no_quotation', $data->no_document)
                ->where('is_active', true)
                ->where('status', 1)
                ->first();
            if (!is_null($sp)) {
                $jadwal = Jadwal::where('is_active', true)
                    ->where('id_sampling', $sp->id)
                    ->select('tanggal')
                    ->first();
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
            $pdf->WriteHTML("</tr></table>");

            $dir = public_path('quotation/');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $filePath = public_path('quotation/' . $fileName);

            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
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

    protected function tanggal_indonesia($tanggal, $mode)
    {
        $bulan = [
            1 => "Januari",
            "Februari",
            "Maret",
            "April",
            "Mei",
            "Juni",
            "Juli",
            "Agustus",
            "September",
            "Oktober",
            "November",
            "Desember",
        ];

        $var = explode("-", $tanggal);
        if ($mode == "period") {
            return $bulan[(int) $var[1]] . " " . $var[0];
        } else {
            return $var[2] . " " . $bulan[(int) $var[1]] . " " . $var[0];
        }
    }

    protected function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }

}