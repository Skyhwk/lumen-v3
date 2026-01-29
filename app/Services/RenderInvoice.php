<?php

namespace App\Services;

use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\Invoice;
use App\Models\QuotationKontrakD;
use App\Models\QrDocument;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use Illuminate\Support\Facades\DB;
use App\Services\MpdfService as Mpdf;

class RenderInvoice
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public function renderInvoice($noInvoice)
    {
        DB::beginTransaction();
        try {
            $invoice = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
                ->first();
            if ($invoice->is_custom == true) {
                $filename = $this->renderCustom($noInvoice);
            } else {
                $filename = $this->renderHeader($noInvoice);
            }
            if (!$filename) {
                throw new \Exception("Gagal membuat file header untuk invoice: $noInvoice");
            }

            // Update invoice dengan filename
            $update = Invoice::where('no_invoice', $noInvoice)->update(['filename' => $filename]);

            // if (!$update) {
            //     throw new \Exception("Invoice dengan nomor $noInvoice tidak ditemukan.");
            // }

            DB::commit();
            return true; // Proses berhasil
        } catch (\Exception $e) {
            DB::rollBack();

            // Lempar ulang exception agar bisa ditangani di tempat lain
            throw $e;
        }
    }

    static function renderHeader($noInvoice)
    {
        try {
            $dataHead = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
                ->first();

            $getDetailQt = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
                ->get();
            // dd($getDetailQt, $noInvoice);
            $data1 = [];
            $harga1 = [];


            foreach ($getDetailQt as $key => $value) {

                $noDoc = explode("/", $value->no_quotation);

                if ($noDoc[1] == 'QTC') {
                    if ($value->periode != "all") {

                        $dataDetail = Invoice::select('invoice.*', 'invoice.nama_perusahaan as perusahaan', 'order_header.*', 'quot_h.no_document', 'quot_h.wilayah', 'quot_d.data_pendukung_sampling', 'quot_d.transportasi', 'quot_d.harga_transportasi_total', 'quot_d.harga_transportasi', 'quot_d.jumlah_orang_24jam AS jam_jumlah_orang_24', 'quot_d.harga_24jam_personil_total', 'quot_d.perdiem_jumlah_orang', 'quot_d.harga_perdiem_personil_total', 'quot_d.biaya_lain', 'quot_d.grand_total', 'quot_d.discount_air', 'quot_d.total_discount_air', 'quot_d.discount_non_air', 'quot_d.total_discount_non_air', 'quot_d.discount_udara', 'quot_d.total_discount_udara', 'quot_d.discount_emisi', 'quot_d.total_discount_emisi', 'quot_d.discount_transport', 'quot_d.total_discount_transport', 'quot_d.discount_perdiem', 'quot_d.total_discount_perdiem', 'quot_d.discount_perdiem_24jam', 'quot_d.total_discount_perdiem_24jam', 'quot_d.discount_gabungan', 'quot_d.total_discount_gabungan', 'quot_d.discount_consultant', 'quot_d.total_discount_consultant', 'quot_d.discount_group', 'quot_d.total_discount_group', 'quot_d.cash_discount_persen', 'quot_d.total_cash_discount_persen', 'quot_d.cash_discount', 'quot_d.custom_discount', 'quot_h.syarat_ketentuan', 'quot_h.keterangan_tambahan', 'quot_d.total_dpp', 'quot_d.total_ppn', 'quot_d.total_pph', 'quot_d.pph', 'quot_d.total_biaya_di_luar_pajak', 'quot_d.piutang', 'quot_d.biaya_akhir', 'quot_h.is_active', 'quot_h.id_cabang', 'quot_d.biaya_preparasi', 'quot_d.total_biaya_preparasi')
                            ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                            ->leftJoin('request_quotation_kontrak_H AS quot_h', 'invoice.no_quotation', '=', 'quot_h.no_document')
                            ->leftJoin('request_quotation_kontrak_D AS quot_d', 'quot_h.id', '=', 'quot_d.id_request_quotation_kontrak_h')
                            ->where('no_invoice', $noInvoice)
                            ->where('quot_h.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('invoice.no_quotation', $value->no_quotation)
                            ->where('quot_d.periode_kontrak', $value->periode)
                            ->orderBy('invoice.no_order')
                            ->first();

                        $hargaDetail = Invoice::select(DB::raw('SUM(quot_d.total_discount) AS diskon, SUM(quot_d.total_ppn) AS ppn, SUM(quot_d.grand_total) AS sub_total, SUM(quot_d.total_pph) AS pph, SUM(quot_d.biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(invoice.piutang) AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_d.total_discount_transport, quot_d.biaya_di_luar_pajak, quot_d.total_discount_perdiem'))
                            ->leftJoin('request_quotation_kontrak_H AS quot_h', 'invoice.no_quotation', '=', 'quot_h.no_document')
                            ->leftJoin('request_quotation_kontrak_D AS quot_d', 'quot_h.id', '=', 'quot_d.id_request_quotation_kontrak_h')
                            ->where('no_invoice', $noInvoice)
                            ->where('quot_d.periode_kontrak', $value->periode)
                            ->where('quot_h.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('quot_h.no_document', $value->no_quotation)
                            ->groupBy('keterangan', 'biaya_di_luar_pajak', 'total_discount_perdiem', 'total_discount_transport')
                            ->first();
                    } else {
                        $dataDetail = Invoice::select('invoice.*', 'invoice.nama_perusahaan as perusahaan', 'order_header.*', 'quot.*')
                            ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                            ->leftJoin(DB::raw('(SELECT no_document, wilayah, data_pendukung_sampling, transportasi, harga_transportasi_total, harga_transportasi, jumlah_orang_24jam AS jam_jumlah_orang_24, harga_24jam_personil_total, perdiem_jumlah_orang, harga_perdiem_personil_total, total_biaya_lain AS biaya_lain, total_biaya_preparasi AS biaya_preparasi_padatan, grand_total, discount_air, total_discount_air, discount_non_air, total_discount_non_air, discount_udara, total_discount_udara, discount_emisi, total_discount_emisi, discount_transport, total_discount_transport, discount_perdiem, total_discount_perdiem, discount_perdiem_24jam, total_discount_perdiem_24jam, discount_gabungan, total_discount_gabungan, discount_consultant, total_discount_consultant, discount_group, total_discount_group, cash_discount_persen, total_cash_discount_persen, cash_discount, custom_discount, syarat_ketentuan, keterangan_tambahan, total_dpp, total_ppn, total_pph, pph, total_biaya_di_luar_pajak, piutang, biaya_akhir, is_active, total_biaya_preparasi AS biaya_preparasi FROM request_quotation_kontrak_H) AS quot'), 'invoice.no_quotation', '=', 'quot.no_document')
                            ->where('no_invoice', $noInvoice)
                            ->where('quot.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('invoice.no_quotation', $value->no_quotation)
                            ->orderBy('invoice.no_order')
                            ->first();

                        $hargaDetail = Invoice::select(DB::raw('quot_h.total_discount AS diskon, quot_h.total_ppn AS ppn, quot_h.grand_total AS sub_total, quot_h.total_pph AS pph, quot_h.biaya_akhir AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, invoice.piutang AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_h.total_discount_transport, quot_h.biaya_diluar_pajak AS biaya_di_luar_pajak, quot_h.total_discount_perdiem'))
                            ->leftJoin('request_quotation_kontrak_H AS quot_h', 'invoice.no_quotation', '=', 'quot_h.no_document')
                            ->where('no_invoice', $noInvoice)
                            ->where('quot_h.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('quot_h.no_document', $value->no_quotation)
                            ->groupBy('keterangan', 'nilai_tagihan', 'quot_h.total_discount', 'quot_h.total_ppn', 'quot_h.grand_total', 'quot_h.total_pph', 'quot_h.biaya_akhir', 'invoice.piutang', 'invoice.total_tagihan', 'biaya_diluar_pajak', 'total_discount_perdiem', 'total_discount_transport')
                            ->first();
                    }

                    array_push($data1, $dataDetail);
                    array_push($harga1, $hargaDetail);
                } else {
                    $dataDetail = Invoice::select('invoice.*', 'invoice.nama_perusahaan as perusahaan', 'order_header.*', 'quot.*')
                        ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                        ->leftJoin(DB::raw('(SELECT no_document, wilayah, data_pendukung_sampling, transportasi, harga_transportasi_total, harga_transportasi, jumlah_orang_24jam AS jam_jumlah_orang_24, harga_24jam_personil_total, perdiem_jumlah_orang, harga_perdiem_personil_total, total_biaya_lain AS biaya_lain, biaya_lain AS keterangan_biaya, biaya_preparasi_padatan, grand_total, discount_air, total_discount_air, discount_non_air, total_discount_non_air, discount_udara, total_discount_udara, discount_emisi, total_discount_emisi, discount_transport, total_discount_transport, discount_perdiem, total_discount_perdiem, discount_perdiem_24jam, total_discount_perdiem_24jam, discount_gabungan, total_discount_gabungan, discount_consultant, total_discount_consultant, discount_group, total_discount_group, cash_discount_persen, total_cash_discount_persen, cash_discount, custom_discount, syarat_ketentuan, keterangan_tambahan, total_dpp, total_ppn, total_pph, pph, biaya_di_luar_pajak, total_biaya_di_luar_pajak, piutang, biaya_akhir, is_active, id_cabang, biaya_preparasi_padatan AS biaya_preparasi FROM request_quotation) AS quot'), 'invoice.no_quotation', '=', 'quot.no_document')
                        ->where('no_invoice', $noInvoice)
                        ->where('invoice.no_quotation', $value->no_quotation)
                        ->where('quot.is_active', true)
                        ->where('invoice.is_active', true)
                        ->orderBy('invoice.no_order')
                        ->first();

                    $hargaDetail = Invoice::select(DB::raw('SUM(total_discount) AS diskon, SUM(total_ppn) AS ppn, SUM(grand_total) AS sub_total, SUM(total_pph) AS pph, SUM(biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(piutang) AS sisa_tagihan, keterangan, SUM(invoice.total_tagihan) AS total_tagihan, total_discount_transport, biaya_di_luar_pajak, total_discount_perdiem'))
                        ->where('no_invoice', $noInvoice)
                        ->where('quot.no_document', $value->no_quotation)
                        ->where('invoice.is_active', true)
                        ->leftJoin(DB::raw('(SELECT no_document, grand_total, total_discount, total_dpp, biaya_akhir, total_ppn, total_pph, total_discount_gabungan, total_discount_consultant, total_discount_group, cash_discount, is_active, total_discount_transport, biaya_di_luar_pajak, total_discount_perdiem FROM request_quotation) AS quot'), 'invoice.no_quotation', '=', 'quot.no_document')
                        ->where('quot.is_active', true)
                        ->groupBy('keterangan', 'biaya_di_luar_pajak', 'total_discount_perdiem', 'total_discount_transport')
                        ->first();

                    array_push($data1, $dataDetail);
                    array_push($harga1, $hargaDetail);
                }
            }


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

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            $pdf->showWatermarkText = true;
            $pdf->keep_table_proportions = true;


            $konsultant = '';
            $jab_pic = '';

            $data = json_decode(json_encode($data1[0]));
            if ($data == null) {
                $area = 'Tangerang';
            } else {
                if ($data->id_cabang == 1)
                    $area = 'Tangerang';
                if ($data->id_cabang == 4)
                    $area = 'Karawang';
                if ($data->id_cabang == 5)
                    $area = 'Pemalang';

                $perusahaan = $data->perusahaan;
            }



            // $strReplace = Helpers::escapeStr('INVOICE_' . $dataHead->no_invoice . '_' . $konsultant);
            $fileName = 'INVOICE' . '_' . preg_replace('/\\//', '_', $dataHead->no_invoice) . '.pdf';
            if ($dataHead->jabatan_pic != '')
                $jab_pic = ' (' . $dataHead->jabatan_pic . ')';

            // $qr_img = '';
            // if ($dataHead->is_generate == 1) {
            //     $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
            //     $qr = DB::table('qr_documents')->where('file', $qr_name)->where('type_document', 'invoice')->first();
            //     if ($qr) $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
            // }

            // $footer = array(
            //     'odd' => array(
            //         'C' => array(
            //             'content' => 'Hal {PAGENO} dari {nbpg}',
            //             'font-size' => 6,
            //             'font-style' => 'I',
            //             'font-family' => 'serif',
            //             'color' => '#606060'
            //         ),
            //         'R' => array(
            //             'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
            //             'font-size' => 5,
            //             'font-style' => 'I',
            //             // 'font-style' => 'B',
            //             'font-family' => 'serif',
            //             'color' => '#000000'
            //         ),
            //         'L' => array(
            //             'content' => '' . $qr_img . '',
            //             'font-size' => 4,
            //             'font-style' => 'I',
            //             // 'font-style' => 'B',
            //             'font-family' => 'serif',
            //             'color' => '#000000'
            //         ),
            //         'line' => -1,
            //     )
            // );

            // $pdf->setFooter($footer);

            $trAlamat = '<tr>
                            <td style="width:35%;"><p style="font-size: 10px;"><u>Alamat Kantor :</u><br><span
                            id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $dataHead->alamat_penagihan . '</span><br><span id="no_tlp_perusahaan">' . $data->no_tlp_perusahaan . '</span><br><span
                            id="nama_pic_order">' . $dataHead->nama_pic . $jab_pic . ' - ' . $dataHead->no_pic . '</span><br><span id="email_pic_order">' . $dataHead->email_pic . '</span></p></td>
                            <td style="width: 30%; text-align: center;"></td>
                        </tr>';

            $pdf->SetHTMLHeader('
                <table class="tabel">
                    <tr class="tr_top">
                        <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                                src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                        </td>
                        <td style="width: 33.33%; text-align: center;">
                            <h5 style="text-align:center; font-size:10px;"><b><u>INVOICE</u></b></h5>
                            <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $dataHead->no_invoice . '
                            </p>
                        </td>
                        <td style="text-align: right;">
                            <p style="font-size: 8px; text-align:right;"><b>PT INTI SURYA
                                    LABORATORIUM</b><br><span
                                    style="white-space: pre-wrap; word-wrap: break-word;">Ruko Icon Business Park blok O no 5 - 6, BSD City
                                    Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang</span><br><span>T : 021-50898988/89 - sales@intilab.com</span><br>www.intilab.com
                            </p>
                        </td>
                    </tr>
                </table>
                <table class="head2" width="100%">
                    <tr>
                        <td colspan="3"><h6 style="font-size:10px; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $perusahaan . '</h6></td>
                    </tr>
                    ' . $trAlamat . '
                </table>
            ');


            $pdf->writeHTML('
                <table style="width:100%;">
                    <tr>
                        <th></th>
            ');

            if ($dataHead->faktur_pajak != null && $dataHead->faktur_pajak != "") {
                $pdf->writeHTML('
                        <th style="text-align:left;padding:5px;font-size:10px;"><b>Faktur Pajak: ' . $dataHead->faktur_pajak . '</b></th> 
                ');
            }

            if ($dataHead->no_po != null && $dataHead->no_po != "") {
                $pdf->writeHTML('
                        <th style="text-align:right;padding:5px;font-size:10px;"><b>No. PO: ' . $dataHead->no_po . '</b></th>  
                ');
            }

            $pdf->writeHTML('
                    </tr>
                </table>
            ');

            $isIV = explode('/', $dataHead->no_invoice)[1] == 'IV' ? true : false;
            if ($isIV) {
                $pdf->writeHTML('
                    <table style="border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                                <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="5">KETERANGAN</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                            </tr>
                        </thead>
                        <tbody>
                ');

                $no = 1;

                foreach ($data1 as $k => $valSampling) {

                    $values = json_decode(json_encode($valSampling));
                    $cekArray = json_decode($values->data_pendukung_sampling);
                    $periode = null;
                    if ($values->periode != null && $values->periode != '' && $values->periode != 'null') {
                        if ($values->periode === 'all') {
                            $periode = 'Semua Periode';
                        } else {
                            $periode = self::tanggal_indonesia($values->periode, 'period');
                        }
                    }

                    $totalBiayaQt = 0;

                    $allPeriode = false;
                    if ($periode === "Semua Periode") {
                        $allPeriode = true;
                    }
                    if ($cekArray == []) {
                        // dd('atas');
                        $tambah = 0;

                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                            $tambah = $tambah + 1;
                        }

                        if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                            $tambah = $tambah + 1;
                        }

                        if (isset($values->keterangan_lainnya)) {
                            $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                        }

                        if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                            if (isset($values->keterangan_biaya_lain)) {

                                if (is_array($values->keterangan_biaya_lain)) {
                                    $tambah = $tambah + count($values->keterangan_biaya_lain);
                                } else {
                                    if (is_array(json_decode($values->keterangan_biaya_lain))) {
                                        $tambah = $tambah + count(json_decode($values->keterangan_biaya_lain));
                                    } else {
                                        $tambah = $tambah + 1;
                                    }
                                }
                            } else {
                                if (is_array(json_decode($values->keterangan_biaya))) {
                                    $tambah = $tambah + count(json_decode($values->keterangan_biaya));
                                } else {
                                    $tambah = $tambah + 1;
                                }
                            }
                        }


                        $rowspan = $tambah + 1;
                        $pdf->writeHTML(
                            '<tr style="border: 1px solid; font-size: 9px;">
                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;">' . $no . '</td>
                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;"><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                        );

                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                            $totalBiayaQt += $values->harga_transportasi_total;
                        }

                        $perdiem_24 = '';
                        $total_perdiem = 0;
                        if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                            $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                            $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                        }

                        if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {

                            if (isset($values->keterangan_perdiem)) {
                                $ket_perdiem = $values->keterangan_perdiem;
                                $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total);
                                $totalBiayaQt += $values->harga_perdiem_personil_total;
                            } else {
                                $ket_perdiem = "Perdiem " . $perdiem_24;
                                $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total + $total_perdiem);
                                $totalBiayaQt += $values->harga_perdiem_personil_total + $total_perdiem;
                            }
                        }

                        if (isset($values->keterangan_lainnya)) {
                            foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                $totalBiayaQt += $ket->harga_total;
                            }
                        }

                        if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                            if (isset($values->keterangan_biaya_lain)) {
                                if (is_array($values->keterangan_biaya_lain)) {
                                    foreach ($values->keterangan_biaya_lain as $biayaLain) {
                                        $totalBiayaQt += $biayaLain->total_biaya;
                                    }
                                } else {
                                    $totalBiayaQt += $values->biaya_lain;
                                }
                            } else {
                                // dd('masuk');
                                $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                if (is_array($biayaLainArray)) {
                                    foreach ($biayaLainArray as $biayaLain) {
                                        $totalBiayaQt += $biayaLain['harga'];
                                    }
                                } else {
                                    $totalBiayaQt += $values->biaya_lain;
                                }
                            }
                        }

                        $pdf->writeHTML('
                            <td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="5">REIMBURSEMENT BIAYA TRANSPORTASI</td>
                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($totalBiayaQt) . '</td></tr>
                        ');
                    } else {
                        if (is_array($cekArray)) { // kondisi array
                            $resetData = reset($cekArray);
                            $usingData = (isset($resetData->data_sampling) && is_array($resetData->data_sampling))
                                ? $resetData->data_sampling
                                : $cekArray;
                            for ($i = 0; $i < count(array_chunk($usingData, 15)); $i++) {
                                foreach (array_chunk($usingData, 15)[$i] as $keys => $dataSampling) {
                                    if ($keys == 0) {
                                        if ($i == count(array_chunk($usingData, 15)) - 1) {
                                            $pdf->writeHTML(
                                                '<tr style="border: 1px solid; font-size: 9px;">
                                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;">' . $no . '</td>
                                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;"><span><b>' . $values->no_order . '</b></span><br/><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                                            );
                                        } else {
                                            $rowspan = count(array_chunk($usingData, 15)[$i]) + 1;
                                            $pdf->writeHTML(
                                                '<tr style="page-break-inside: avoid; border: 1px solid; font-size: 9px;">
                                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;">' . $no . '</td>
                                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;"><span><b>' . $values->no_order . '</b></span><br/><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                                            );
                                        }
                                    }

                                    // dd($dataSampling);
                                    // $kategori2 = explode("-", $dataSampling->kategori_2);
                                    $split = explode("/", $values->no_document);
                                    if ($split[1] == 'QTC') {
                                        if (isset($dataSampling->keterangan_pengujian)) {
                                            $totalBiayaQt += $dataSampling->harga_total;
                                        } else {
                                            $totalBiayaQt += $allPeriode ? $dataSampling->harga_satuan * ($dataSampling->jumlah_titik) * (count($dataSampling->periode)) : $dataSampling->harga_satuan * ($dataSampling->jumlah_titik);
                                        }
                                    } else {
                                        $totalBiayaQt += $dataSampling->harga_total;
                                    }
                                }

                                $isLastElement = $i == count(array_chunk($usingData, 15)) - 1;

                                if ($isLastElement) {
                                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                        // dump('Transport', $values->harga_transportasi_total);
                                        $totalBiayaQt += $values->harga_transportasi_total;
                                    }

                                    $perdiem_24 = '';
                                    $total_perdiem = 0;
                                    if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                        $perdiem_24 = 'Termasuk Perdiem (24 Jam)';

                                        // dump('penjulamlahan total Pardiem', $total_perdiem);
                                        $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                                    }

                                    if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                        // dd($values->satuan_perdiem, $values->harga_perdiem_personil_total);
                                        if (isset($values->keterangan_perdiem)) {
                                            // dump('plus biaya pardiem atas', $values->harga_perdiem_personil_total);
                                            $totalBiayaQt += $values->harga_perdiem_personil_total;
                                        } else {
                                            // dump('plus biaya pardiem bawah', ($values->harga_perdiem_personil_total + $total_perdiem));
                                            $totalBiayaQt += $values->harga_perdiem_personil_total + $total_perdiem;
                                        }
                                    }

                                    if (isset($values->keterangan_lainnya)) {
                                        foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                            // dump('plus biaya keterangan', $ket->harga_total);
                                            $totalBiayaQt += $ket->harga_total;
                                        }
                                    }

                                    if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                                        if (isset($values->keterangan_biaya_lain)) {
                                            // dump('plus biaya Lain atas', $values->biaya_lain);
                                            $totalBiayaQt += $values->biaya_lain;
                                        } else {
                                            $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                            if (is_array($biayaLainArray)) {
                                                foreach ($biayaLainArray as $biayaLain) {
                                                    // dump('plus biaya Lain foreach', $biayaLain['harga']);
                                                    $totalBiayaQt += $biayaLain['harga'];
                                                }
                                            } else {
                                                // dump('plus biaya Lain bawah', $values->biaya_lain);
                                                $totalBiayaQt += $values->biaya_lain;
                                            }
                                        }
                                    }
                                    // dd('Akhir',$totalBiayaQt);
                                    $pdf->writeHTML('
                                        <td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="5">REIMBURSEMENT BIAYA TRANSPORTASI</td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($totalBiayaQt) . '</td></tr>
                                    ');
                                }
                            }
                        } else { // kondisi object
                            // dd('bawah');
                            foreach (json_decode($values->data_pendukung_sampling) as $keys => $dataSampling) {

                                $tambah = 0;

                                if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                    $tambah = $tambah + 1;
                                }

                                if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                                    $tambah = $tambah + 1;
                                }

                                if ($values->biaya_lain != null) {
                                    $tambah = $tambah + count(json_decode($values->biaya_lain));
                                }

                                if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                    $tambah = $tambah + 1;
                                }

                                if (isset($values->keterangan_lainnya)) {
                                    $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                                }
                                //cek ada berapa pengujian, jika lebih dari 17 pengujian maka akan add page baru
                                for ($i = 0; $i < count(array_chunk($dataSampling->data_sampling, 15)); $i++) {

                                    foreach (array_chunk($dataSampling->data_sampling, 15)[$i] as $key => $datasp) {

                                        if ($values->periode != null) {
                                            $pr = self::tanggal_indonesia($values->periode, 'period');
                                        } else {
                                            $pr = "";
                                        }

                                        if ($key == 0) {


                                            if ($i == count(array_chunk($dataSampling->data_sampling, 15)) - 1) {
                                                $rowspan = count(array_chunk($dataSampling->data_sampling, 15)[$i]) + 1 + $tambah;

                                                $pdf->writeHTML(
                                                    '<tr style="border: 1px solid; font-size: 9px;">
                                                        <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;">' . $no . '</td>
                                                        <td style="font-size:9px; border:1px solid;border-color:#000; padding:5px;"><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '</b></span><br><span><b>' . $pr . '</b></span</td>'
                                                );
                                            } else {
                                                $rowspan = count(array_chunk($dataSampling->data_sampling, 15)[$i]) + 1;
                                                $pdf->writeHTML(
                                                    '<tr style="page-break-inside: avoid; border: 1px solid; font-size: 9px;">
                                                        <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" >' . $no . '</td>
                                                        <td style="font-size:9px; border:1px solid;border-color:#000; padding:5px;" ><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '</b></span><br><span><b>' . $pr . '</b></span</td>'
                                                );
                                            }
                                        }

                                        $kategori2 = explode("-", $datasp->kategori_2);
                                        if (isset($datasp->keterangan_pengujian)) {
                                            $keterangan_pengujian = $datasp->keterangan_pengujian;
                                            $harga_total = $datasp->harga_total;
                                        } else {
                                            $keterangan_pengujian = strtoupper($kategori2[1]) . ' - ' . $datasp->total_parameter . ' Parameter';
                                            $harga_total = $datasp->harga_satuan * $datasp->jumlah_titik;



                                            if (is_string($datasp->regulasi)) {
                                                $decodedRegulasi = json_decode($datasp->regulasi, true);
                                                $datasp->regulasi = $decodedRegulasi ?: [];
                                            }

                                            if (!is_array($datasp->regulasi)) {
                                                $datasp->regulasi = [];
                                            }

                                            foreach ($datasp->regulasi as $rg => $v) {
                                                $reg = '';

                                                if ($v != '') {
                                                    $regulasi = explode("-", $v);
                                                    $reg = $regulasi[1];
                                                }
                                            }
                                        }

                                        $totalBiayaQt += $harga_total;
                                    }

                                    $isLastElement = $i == count(array_chunk($dataSampling->data_sampling, 15)) - 1;

                                    if ($isLastElement) {

                                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                                            if (isset($values->keterangan_transportasi)) {
                                                $keterangan_transportasi = $values->keterangan_transportasi;
                                            } else {

                                                $keterangan_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                            }
                                            $totalBiayaQt += $values->harga_transportasi_total;
                                        }


                                        $perdiem_24 = '';
                                        $total_perdiem = 0;
                                        if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                            $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                            $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                                        }


                                        if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                            if (isset($values->keterangan_perdiem)) {
                                                $keterangan_perdiem = $values->keterangan_perdiem;
                                                $harga_perdiem = $values->harga_perdiem_personil_total;
                                                $jml_perdiem = $values->perdiem_jumlah_orang;
                                                if (isset($values->satuan_perdiem)) {
                                                    $satuan_perdiem = self::rupiah($values->satuan_perdiem);
                                                } else {
                                                    if ($values->harga_perdiem_personil_total == 0) {
                                                        $jml_perdiem = '';
                                                        $satuan_perdiem = '';
                                                        continue;
                                                    } else {
                                                        $sdiem = $harga_perdiem / $jml_perdiem;
                                                        $satuan_perdiem = self::rupiah($sdiem);
                                                    }
                                                }
                                            } else {
                                                $keterangan_perdiem = "Perdiem " . $perdiem_24;
                                                $harga_perdiem = $values->harga_perdiem_personil_total + $total_perdiem;
                                                $jml_perdiem = '';
                                                $satuan_perdiem = '';
                                            }
                                            $totalBiayaQt += $harga_perdiem;
                                        }

                                        if (isset($values->keterangan_lainnya)) {
                                            foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                                $totalBiayaQt += $ket->harga_total;
                                            }
                                        }

                                        if ($values->biaya_lain != null) {
                                            foreach (json_decode($values->biaya_lain) as $b => $biayaL) {
                                                $totalBiayaQt += $biayaL->harga;
                                            }
                                        }

                                        if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                            $totalBiayaQt += $values->total_biaya_preparasi;
                                        }

                                        $pdf->writeHTML('
                                            <td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="5">REIMBURSEMENT BIAYA TRANSPORTASI</td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($totalBiayaQt) . '</td></tr>
                                        ');
                                    }
                                }
                            }
                        }
                    }

                    $no++;
                }
            } else {
                $pdf->writeHTML('
                    <table style="border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                                <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="3">KETERANGAN PENGUJIAN</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">TITIK</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">HARGA SATUAN</th>
                                <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                            </tr>
                        </thead>
                        <tbody>
                ');

                $no = 1;


                foreach ($data1 as $k => $valSampling) {

                    $values = json_decode(json_encode($valSampling));
                    $cekArray = json_decode($values->data_pendukung_sampling);
                    $periode = null;
                    if ($values->periode != null && $values->periode != '' && $values->periode != 'null') {
                        if ($values->periode === 'all') {
                            $periode = 'Semua Periode';
                        } else {
                            $periode = self::tanggal_indonesia($values->periode, 'period');
                        }
                    }

                    $allPeriode = false;
                    if ($periode === "Semua Periode") {
                        $allPeriode = true;
                    }
                    if ($cekArray == []) {
                        // dd('atas');
                        $tambah = 0;

                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                            $tambah = $tambah + 1;
                        }

                        if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                            $tambah = $tambah + 1;
                        }

                        if (isset($values->keterangan_lainnya)) {
                            $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                        }

                        if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                            if (isset($values->keterangan_biaya_lain)) {

                                if (is_array($values->keterangan_biaya_lain)) {
                                    $tambah = $tambah + count($values->keterangan_biaya_lain);
                                } else {
                                    if (is_array(json_decode($values->keterangan_biaya_lain))) {
                                        $tambah = $tambah + count(json_decode($values->keterangan_biaya_lain));
                                    } else {
                                        $tambah = $tambah + 1;
                                    }
                                }
                            } else {
                                if (is_array(json_decode($values->keterangan_biaya))) {
                                    $tambah = $tambah + count(json_decode($values->keterangan_biaya));
                                } else {
                                    $tambah = $tambah + 1;
                                }
                            }
                        }


                        $rowspan = $tambah + 1;
                        $pdf->writeHTML(
                            '<tr style="border: 1px solid; font-size: 9px;">
                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $rowspan . '">' . $no . '</td>
                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . $rowspan . '"><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                        );

                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                            if (isset($values->keterangan_transportasi)) {
                                $ket_transportasi = $values->keterangan_transportasi;
                            } else {
                                $ket_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                            }

                            $pdf->writeHTML('
                                <tr>
                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $ket_transportasi . '</td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $values->transportasi . '</td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi) . '</td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total) . '</td>
                                </tr>
                            ');
                        }

                        $perdiem_24 = '';
                        $total_perdiem = 0;
                        if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                            $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                            $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                        }

                        if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {

                            if (isset($values->keterangan_perdiem)) {
                                $ket_perdiem = $values->keterangan_perdiem;
                                $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total);
                            } else {
                                $ket_perdiem = "Perdiem " . $perdiem_24;
                                $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total + $total_perdiem);
                            }

                            $pdf->writeHTML('
                                <tr>
                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket_perdiem . '</td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $haga_perdiem_non . '</td>
                                </tr>
                            ');
                        }

                        if (isset($values->keterangan_lainnya)) {
                            foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {

                                $pdf->writeHTML('
                                    <tr>
                                        <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">Biaya : ' . $ket->deskripsi . '</td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $ket->titik . '</td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_satuan) . '</td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_total) . '</td>
                                    </tr>
                                ');
                            }
                        }

                        if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                            if (isset($values->keterangan_biaya_lain)) {
                                if (is_array($values->keterangan_biaya_lain)) {
                                    foreach ($values->keterangan_biaya_lain as $biayaLain) {
                                        $pdf->writeHTML('
                                            <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3"' . $biayaLain->deskripsi . '</td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain->harga) . '</td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain->total_biaya) . '</td></tr>
                                        ');
                                    }
                                } else {
                                    $pdf->writeHTML('
                                        <tr>
                                            <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . self::rupiah($values->keterangan_biaya_lain) . '</td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td>
                                        </tr>
                                    ');
                                }
                            } else {
                                // dd('masuk');
                                $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                if (is_array($biayaLainArray)) {
                                    foreach ($biayaLainArray as $biayaLain) {
                                        $pdf->writeHTML('
                                            <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">Biaya : ' . $biayaLain['deskripsi'] . '</td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain['harga']) . '</td></tr>
                                        ');
                                    }
                                } else {
                                    $pdf->writeHTML('
                                        <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">Biaya Lain-Lain</td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td></tr>
                                    ');
                                }
                            }
                        }
                    } else {
                        if (is_array($cekArray)) { // kondisi array
                            // dd('tengah');
                            $tambah = 0;
                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                $tambah = $tambah + 1;
                            }

                            if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                                $tambah = $tambah + 1;
                            }

                            if ($values->biaya_lain) {
                                if (is_array(json_decode($values->biaya_lain))) {
                                    $tambah = $tambah + count(json_decode($values->biaya_lain));
                                } else {
                                    $tambah = $tambah + 1;
                                }
                            }

                            if (isset($values->keterangan_lainnya)) {
                                $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                            }
                            // dd($cekArray);
                            $resetData = reset($cekArray);
                            $usingData = (isset($resetData->data_sampling) && is_array($resetData->data_sampling))
                                ? $resetData->data_sampling
                                : $cekArray;
                            // dd($usingData);
                            $chunks = self::chunkByContentHeight($usingData, $tambah);
                            // dd($usingData, $tambah, $chunks);
                            for ($i = 0; $i < count($chunks); $i++) {
                                foreach ($chunks[$i] as $keys => $dataSampling) {
                                    if ($keys == 0) {
                                        if ($i == count($chunks) - 1) {
                                            $rowspan = count($chunks[$i]) + 1 + $tambah;
                                            $pdf->writeHTML(
                                                '<tr style="border: 1px solid; font-size: 9px;">
                                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $rowspan . '">' . $no . '</td>
                                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . $rowspan . '"><span><b>' . $values->no_order . '</b></span><br/><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                                            );
                                        } else {
                                            $rowspan = count($chunks[$i]) + 1;
                                            $pdf->writeHTML(
                                                '<tr style="page-break-inside: avoid; border: 1px solid; font-size: 9px;">
                                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $rowspan . '">' . $no . '</td>
                                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . $rowspan . '"><span><b>' . $values->no_order . '</b></span><br/><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                                            );
                                        }
                                    }


                                    $kategori2 = explode("-", $dataSampling->kategori_2);
                                    $split = explode("/", $values->no_document);

                                    if ($split[1] == 'QTC') {
                                        if (isset($dataSampling->keterangan_pengujian)) {
                                            $total_harga_qtc = self::rupiah($dataSampling->harga_total);
                                            $pdf->writeHTML('
                                            <tr>
                                            <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $dataSampling->keterangan_pengujian . ' Parameter</span><br>
                                            ');
                                        } else {
                                            $total_harga_qtc = self::rupiah($allPeriode ? $dataSampling->harga_satuan * ($dataSampling->jumlah_titik) * (count($dataSampling->periode)) : $dataSampling->harga_satuan * ($dataSampling->jumlah_titik));
                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter . ' Parameter</span><br>
                                            ');

                                            foreach ($dataSampling->regulasi as $rg => $v) {
                                                $reg = '';

                                                if ($v != '') {
                                                    $regulasi = explode("-", $v);
                                                    $reg = $regulasi[1];
                                                }

                                                if ($rg == 0) {
                                                    $pdf->WriteHTML('<span style="font-size: 9px;">' . $reg . "</span>");
                                                } else {
                                                    $pdf->WriteHTML('<br><span style="font-size: 9px;">' . $reg . "</span>");
                                                }
                                            }
                                        }


                                        $pdf->writeHTML('
                                                </td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-center">' . ($allPeriode ? $dataSampling->jumlah_titik * count($dataSampling->periode) : $dataSampling->jumlah_titik) . '</td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-right">' . self::rupiah($dataSampling->harga_satuan) . '</td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-right">' . $total_harga_qtc . '</td>
                                            </tr>
                                        
                                        ');
                                    } else {

                                        if (isset($dataSampling->keterangan_pengujian)) {

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $dataSampling->keterangan_pengujian . '</span><br>
                                            ');
                                        } else {

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter . ' Parameter</span><br>
                                            ');


                                            if (is_array($dataSampling->regulasi)) {

                                                foreach ($dataSampling->regulasi as $rg => $v) {
                                                    $reg = '';

                                                    if ($v != '') {
                                                        $regulasi = explode("-", $v);
                                                        $reg = $regulasi[1];
                                                    }

                                                    if ($rg == 0) {
                                                        $pdf->WriteHTML('<span style="font-size: 9px;">' . $reg . "</span>");
                                                    } else {
                                                        $pdf->WriteHTML('<br><span style="font-size: 9px;">' . $reg . "</span>");
                                                    }
                                                }
                                            }
                                        }


                                        $pdf->writeHTML('
                                                </td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $dataSampling->jumlah_titik . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($dataSampling->harga_satuan) . '</td>
                                                <td style="border: 1px solid; font-size:9px; text-align:center" class="text-right">' . self::rupiah($dataSampling->harga_total) . '</td>
                                            </tr>
                                        ');
                                    }
                                }

                                $isLastElement = $i == count($chunks) - 1;

                                if ($isLastElement) {
                                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                                        if (isset($values->keterangan_transportasi)) {
                                            $ket_transportasi = $values->keterangan_transportasi;
                                        } else {
                                            $ket_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                        }

                                        $pdf->writeHTML('
                                            <tr>
                                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $ket_transportasi . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $values->transportasi . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total / $values->transportasi) . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total) . '</td>
                                            </tr>
                                        ');
                                    }

                                    $perdiem_24 = '';
                                    $total_perdiem = 0;
                                    if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                        $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                        $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                                    }

                                    if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                        // dd($values->satuan_perdiem, $values->harga_perdiem_personil_total);
                                        if (isset($values->keterangan_perdiem)) {
                                            $ket_perdiem = $values->keterangan_perdiem;
                                            $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total);
                                            $jml_perdiem = $values->perdiem_jumlah_orang;
                                            if (isset($values->satuan_perdiem)) {
                                                $satuan_perdiem = self::rupiah($values->satuan_perdiem);
                                            } else {
                                                $sdiem = $harga_perdiem_personil_total / $jml_perdiem;
                                                $satuan_perdiem = self::rupiah($sdiem);
                                            }
                                        } else {
                                            $ket_perdiem = "Perdiem " . $perdiem_24;
                                            $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total + $total_perdiem);
                                            $jml_perdiem = '';
                                            $satuan_perdiem = $haga_perdiem_non;
                                        }
                                        $pdf->writeHTML('
                                            <tr>
                                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $jml_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $satuan_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $haga_perdiem_non . '</td>
                                            </tr>
                                        ');
                                    }

                                    if (isset($values->keterangan_lainnya)) {
                                        foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket->deskripsi . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $ket->titik . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_satuan) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_total) . '</td>
                                                </tr>
                                            ');
                                        }
                                    }

                                    if ($values->biaya_lain != null) {
                                        if (isset($values->keterangan_biaya_lain)) {
                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . self::rupiah($values->keterangan_biaya_lain) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td>
                                                </tr>
                                            ');
                                        } else {
                                            $biayaLainArray = json_decode($values->biaya_lain, true);
                                            if (is_array($biayaLainArray)) {
                                                foreach ($biayaLainArray as $biayaLain) {
                                                    $pdf->writeHTML('
                                                        <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">' . $biayaLain['deskripsi'] . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain['harga']) . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain['harga']) . '</td></tr>
                                                    ');
                                                }
                                            } else {
                                                $pdf->writeHTML('
                                                    <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">Biaya Lain-Lain</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td></tr>
                                                ');
                                            }
                                        }
                                    }
                                }
                            }

                            if (empty($chunks)) {
                                $tambah++;
                                $pdf->writeHTML(
                                    '<tr style="border: 1px solid; font-size: 9px;">
                                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $tambah . '">' . $no . '</td>
                                                <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . $tambah . '"><span><b>' . $values->no_order . '</b></span><br/><span><b>' . $values->no_document . '<br/>' . ($periode ? $periode : '') . '</b></span></td>'
                                );

                                if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                                    if (isset($values->keterangan_transportasi)) {
                                        $ket_transportasi = $values->keterangan_transportasi;
                                    } else {
                                        $ket_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                    }

                                    $pdf->writeHTML('
                                            <tr>
                                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $ket_transportasi . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $values->transportasi . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total / $values->transportasi) . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total) . '</td>
                                            </tr>
                                        ');
                                }

                                $perdiem_24 = '';
                                $total_perdiem = 0;
                                if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                    $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                    $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                                }

                                if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                    // dd($values->satuan_perdiem, $values->harga_perdiem_personil_total);
                                    if (isset($values->keterangan_perdiem)) {
                                        $ket_perdiem = $values->keterangan_perdiem;
                                        $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total);
                                        $jml_perdiem = $values->perdiem_jumlah_orang;
                                        if (isset($values->satuan_perdiem)) {
                                            $satuan_perdiem = self::rupiah($values->satuan_perdiem);
                                        } else {
                                            $sdiem = $harga_perdiem_personil_total / $jml_perdiem;
                                            $satuan_perdiem = self::rupiah($sdiem);
                                        }
                                    } else {
                                        $ket_perdiem = "Perdiem " . $perdiem_24;
                                        $haga_perdiem_non = self::rupiah($values->harga_perdiem_personil_total + $total_perdiem);
                                        $jml_perdiem = '';
                                        $satuan_perdiem = $haga_perdiem_non;
                                    }
                                    $pdf->writeHTML('
                                            <tr>
                                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $jml_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $satuan_perdiem . '</td>
                                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $haga_perdiem_non . '</td>
                                            </tr>
                                        ');
                                }

                                if (isset($values->keterangan_lainnya)) {
                                    foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                        $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket->deskripsi . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $ket->titik . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_satuan) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_total) . '</td>
                                                </tr>
                                            ');
                                    }
                                }

                                if ($values->biaya_lain != null) {
                                    if (isset($values->keterangan_biaya_lain)) {
                                        $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . self::rupiah($values->keterangan_biaya_lain) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td>
                                                </tr>
                                            ');
                                    } else {
                                        $biayaLainArray = json_decode($values->biaya_lain, true);
                                        if (is_array($biayaLainArray)) {
                                            foreach ($biayaLainArray as $biayaLain) {
                                                $pdf->writeHTML('
                                                        <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">' . $biayaLain['deskripsi'] . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain['harga']) . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaLain['harga']) . '</td></tr>
                                                    ');
                                            }
                                        } else {
                                            $pdf->writeHTML('
                                                    <tr><td style="border: 1px solid; font-size: 9px; padding:5px;" colspan="3">Biaya Lain-Lain</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->biaya_lain) . '</td></tr>
                                                ');
                                        }
                                    }
                                }
                            }
                        } else { // kondisi object
                            // dd('bawah');

                            foreach (json_decode($values->data_pendukung_sampling) as $keys => $dataSampling) {

                                $tambah = 0;

                                if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                    $tambah = $tambah + 1;
                                }

                                if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                                    $tambah = $tambah + 1;
                                }

                                if ($values->biaya_lain != null) {
                                    $tambah = $tambah + count(json_decode($values->biaya_lain));
                                }

                                if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                    $tambah = $tambah + 1;
                                }

                                if (isset($values->keterangan_lainnya)) {
                                    $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                                }

                                $extra_row = 0;
                                if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                    $extra_row++;
                                }

                                if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                    $extra_row++;
                                }

                                if (isset($values->keterangan_lainnya)) {
                                    foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                        $extra_row++;
                                    }
                                }

                                if ($values->biaya_lain != null) {
                                    foreach (json_decode($values->biaya_lain) as $b => $biayaL) {
                                        $extra_row++;
                                    }
                                }

                                if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                    $extra_row++;
                                }

                                $chunks = self::chunkByContentHeight($dataSampling->data_sampling, $extra_row);
                                // dd($extra_row_new_page);
                                $no_order_render_now = '';
                                for ($i = 0; $i < count($chunks); $i++) {

                                    foreach ($chunks[$i] as $key => $datasp) {
                                        if ($values->periode != null) {
                                            $pr = self::tanggal_indonesia($values->periode, 'period');
                                        } else {
                                            $pr = "";
                                        }

                                        if ($key == 0) {


                                            if ($i == count($chunks) - 1) {
                                                $rowspan = count($chunks[$i]) + 1 + $tambah;
                                                $is_order_same = $no_order_render_now == $values->no_order;
                                                if ($is_order_same) {
                                                    // $no = '';
                                                    // $values->no_order = '';
                                                    // $values->no_document = '';
                                                    // $pr = '';
                                                    $pdf->writeHTML('
                                                            </tbody>
                                                        </table>
                                                    ');
                                                    $pdf->AddPage();
                                                    $pdf->writeHTML('
                                                        <table style="border-collapse: collapse;">
                                                            <thead>
                                                                <tr>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                                                                    <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="3">KETERANGAN PENGUJIAN</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">TITIK</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">HARGA SATUAN</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                    ');
                                                }
                                                $pdf->writeHTML(
                                                    '<tr style="border: 1px solid; font-size: 9px;">
                                                        <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $rowspan . '">' . $no . '</td>
                                                        <td style="font-size:9px; border:1px solid;border-color:#000; padding:5px;" rowspan="' . $rowspan . '"><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '</b></span><br><span><b>' . $pr . '</b></span</td>'
                                                );
                                                $no_order_render_now = $values->no_order;
                                            } else {
                                                $is_order_same = $no_order_render_now == $values->no_order;
                                                if ($is_order_same) {
                                                    // $no = '';
                                                    // $values->no_order = '';
                                                    // $values->no_document = '';
                                                    // $pr = '';
                                                    $pdf->writeHTML('
                                                            </tbody>
                                                        </table>
                                                    ');
                                                    $pdf->AddPage();
                                                    $pdf->writeHTML('
                                                        <table style="border-collapse: collapse;">
                                                            <thead>
                                                                <tr>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                                                                    <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="3">KETERANGAN PENGUJIAN</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">TITIK</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">HARGA SATUAN</th>
                                                                    <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                    ');
                                                }
                                                $rowspan = count($chunks[$i]) + 1;
                                                $pdf->writeHTML(
                                                    '<tr style="page-break-inside: avoid; border: 1px solid; font-size: 9px;">
                                                        <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . $rowspan . '">' . $no . '</td>
                                                        <td style="font-size:9px; border:1px solid;border-color:#000; padding:5px;" rowspan="' . $rowspan . '"><span><b>' . $values->no_order . '</b></span><br><span><b>' . $values->no_document . '</b></span><br><span><b>' . $pr . '</b></span</td>'
                                                );
                                                $no_order_render_now = $values->no_order;
                                            }
                                        }

                                        $kategori2 = explode("-", $datasp->kategori_2);
                                        if (isset($datasp->keterangan_pengujian)) {
                                            $keterangan_pengujian = $datasp->keterangan_pengujian;
                                            $harga_total = self::rupiah($datasp->harga_total);

                                            $pdf->writeHTML('
                                            <tr>
                                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $keterangan_pengujian . '</span><br>
                                        ');
                                        } else {
                                            $keterangan_pengujian = strtoupper($kategori2[1]) . ' - ' . $datasp->total_parameter . ' Parameter';
                                            $harga_total = self::rupiah($datasp->harga_satuan * $datasp->jumlah_titik);

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $keterangan_pengujian . '</span><br>
                                            ');

                                            if (is_string($datasp->regulasi)) {
                                                $decodedRegulasi = json_decode($datasp->regulasi, true);
                                                $datasp->regulasi = $decodedRegulasi ?: [];
                                            }

                                            if (!is_array($datasp->regulasi)) {
                                                $datasp->regulasi = [];
                                            }

                                            foreach ($datasp->regulasi as $rg => $v) {
                                                $reg = '';

                                                if ($v != '') {
                                                    $regulasi = explode("-", $v);
                                                    $reg = $regulasi[1];
                                                }

                                                if ($rg == 0) {
                                                    $pdf->WriteHTML('<span style="font-size: 9px;">' . $reg . "</span>");
                                                } else {
                                                    $pdf->WriteHTML('<br><span style="font-size: 9px;">' . $reg . "</span>");
                                                }
                                            }
                                        }


                                        $pdf->writeHTML('
                                                </td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-center">' . $datasp->jumlah_titik . '</td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-right">' . self::rupiah($datasp->harga_satuan) . '</td>
                                                <td style="border: 1px solid; font-size: 9px;text-align:center;" class="text-right">' . $harga_total . '</td>
                                            </tr>
                                        ');
                                    }

                                    $isLastElement = $i == count($chunks) - 1;

                                    if ($isLastElement) {

                                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                                            if (isset($values->keterangan_transportasi)) {
                                                $keterangan_transportasi = $values->keterangan_transportasi;
                                            } else {

                                                $keterangan_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                            }

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $keterangan_transportasi . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $values->transportasi . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total / $values->transportasi) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->harga_transportasi_total) . '</td>
                                                </tr>
                                            ');
                                        }


                                        $perdiem_24 = '';
                                        $total_perdiem = 0;
                                        if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                            $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                            $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                                        }


                                        if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                            if (isset($values->keterangan_perdiem)) {
                                                $keterangan_perdiem = $values->keterangan_perdiem;
                                                $harga_perdiem = self::rupiah($values->harga_perdiem_personil_total);
                                                $jml_perdiem = $values->perdiem_jumlah_orang;
                                                if (isset($values->satuan_perdiem)) {
                                                    $satuan_perdiem = self::rupiah($values->satuan_perdiem);
                                                } else {
                                                    if ($values->harga_perdiem_personil_total == 0) {
                                                        $jml_perdiem = '';
                                                        $satuan_perdiem = '';
                                                        continue;
                                                    } else {
                                                        $sdiem = $harga_perdiem / $jml_perdiem;
                                                        $satuan_perdiem = self::rupiah($sdiem);
                                                    }
                                                }
                                            } else {
                                                $keterangan_perdiem = "Perdiem " . $perdiem_24;
                                                $harga_perdiem = self::rupiah($values->harga_perdiem_personil_total + $total_perdiem);
                                                $jml_perdiem = '';
                                                $satuan_perdiem = '';
                                            }

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $keterangan_perdiem . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $jml_perdiem . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $satuan_perdiem . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $harga_perdiem . '</td>
                                                </tr>
                                            ');
                                        }

                                        if (isset($values->keterangan_lainnya)) {
                                            foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                                $pdf->writeHTML('
                                                    <tr>
                                                        <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $ket->deskripsi . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $ket->titik . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_satuan) . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($ket->harga_total) . '</td>
                                                    </tr>
                                                ');
                                            }
                                        }

                                        if ($values->biaya_lain != null) {
                                            foreach (json_decode($values->biaya_lain) as $b => $biayaL) {
                                                $qtyB = isset($biayaL->qty) ? $biayaL->qty : '';
                                                $hargaSatuanB = isset($biayaL->harga_satuan) ? self::rupiah($biayaL->harga_satuan) : '';
                                                $pdf->writeHTML('
                                                    <tr>
                                                        <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">Biaya : ' . $biayaL->deskripsi . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $qtyB . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $hargaSatuanB . '</td>
                                                        <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($biayaL->harga) . '</td>
                                                    </tr>
                                                ');
                                            }
                                        }

                                        if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                            $a = json_decode($values->biaya_preparasi);
                                            $collection = collect($a);

                                            $pdf->writeHTML('
                                                <tr>
                                                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3">' . $collection->first()->Deskripsi . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center"></td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($collection->first()->Harga) . '</td>
                                                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($values->total_biaya_preparasi) . '</td>
                                                </tr>
                                            ');
                                        }
                                    }
                                }
                            }
                        }
                    }

                    $no++;
                }
            }


            //declare variable
            $sub_total = 0;
            $diskon = 0;
            $ppn = 0;
            $pph = 0;
            $total_harga = 0;
            $nilai_tagihan = 0;
            $total_tagihan = 0;
            $sisa_tagihan = 0;
            $pajak = 0;

            foreach ($harga1 as $detailHargaInvo) {

                $el = json_decode(json_encode($detailHargaInvo));

                //cek apakah ada biaya diluar pajak
                if (isset($el->biaya_di_luar_pajak)) {
                    $biayaDiLuarPajak = json_decode($el->biaya_di_luar_pajak);

                    if ($biayaDiLuarPajak->select != []) {
                        $luarPajak = round($el->total_discount_transport) + round($el->total_discount_perdiem);
                        $totDisk = $el->diskon == null ? 0 : round($el->diskon) - $luarPajak;
                        $pajak = 0;
                    } else {
                        $totDisk = $el->diskon == null ? 0 : round($el->diskon);
                        $pajak = 1;
                    }

                    $sub_total += (int) round($el->sub_total);
                    $diskon += $totDisk;
                    $ppn += $el->ppn == null ? 0 : round($el->ppn);
                    $pph += $el->pph == null ? 0 : round($el->pph);
                    $total_harga += (int) round($el->total_harga);
                    $nilai_tagihan += (int) round($el->nilai_tagihan);
                    $total_tagihan += (int) round($el->total_tagihan);
                    $sisa_tagihan += (int) round($el->sisa_tagihan);
                } else {

                    $sub_total += (int) round($el->sub_total);
                    $diskon += $el->diskon == null ? 0 : round($el->diskon);
                    $ppn += $el->ppn == null ? 0 : round($el->ppn);
                    $pph += $el->pph == null ? 0 : round($el->pph);
                    $total_harga += (int) round($el->total_harga);
                    $nilai_tagihan += (int) round($el->nilai_tagihan);
                    $total_tagihan += (int) round($el->total_tagihan);
                    $sisa_tagihan += (int) round($el->sisa_tagihan);
                    $pajak = 1;
                }
            };
            $spk = '';
            if ($dataHead->no_spk != null && $dataHead->no_faktur != null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . ' - No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_faktur != null && $dataHead->no_spk == null) {
                $spk = '<span style="font-size:10px">- No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_spk != null && $dataHead->no_faktur == null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . '</span>';
            } else {
                $spk = '';
            }

            $space = '<p style="font-size:4px;">&nbsp;</p>';
            $spaceSection = '<p style="font-size:8px;">&nbsp;</p>';

            $pdf->writeHTML('
            <tr><td style="height: 7px;" colspan="5"></td></tr>
            <tr class="line_">
                <td rowspan="11" colspan="5" style="padding-right:10px;">
                    <div>
                        <p style="line-height:normal; font-size:9px;">Terbilang: </p>
                        ' . $space . '
                        <p><b style="font-size:9px; text-align:center; white-space:normal; line-height:normal;">' . self::terbilang($nilai_tagihan) . ' Rupiah</b></p>
                        ' . $spaceSection . '
                        <p style="font-size:10px; border-bottom:1px solid #000; width: 120px;">Keterangan Pembayaran:</p>
                        ' . $space . '
                        <p style="font-size:10px;">- Pembayaran dilakukan secara <b style="font-style: italic;">"Full Amount"</b> (tanpa pemotongan biaya apapun)</p>
                        <p style="font-size:10px;">- <b>Cash / Transfer : ' . $dataHead->rekening . ' atas nama PT Inti Surya Laboratorium Bank Central Asia (BCA) - Kota Tangerang - Cabang BSD Serpong</b></p>
                        <p style="font-size:10px">- Pembayaran baru dianggap sah apabila cek / giro telah dapat dicairkan</p>
                        <p style="font-size:10px">- Bukti Pembayaran agar dapat di e-mail ke : billing@intilab.com</p>
                        <p style="font-size:10px">- Invoice asli ini berlaku juga sebagai kwitansi asli yang sah</p>
                        ' . $spk . '
            ');

            if ($dataHead->keterangan_tambahan && count($dataHead->keterangan_tambahan) > 0) {

                $pdf->writeHTML(
                    $spaceSection . '
                    <p style="font-size:10px; border-bottom:1px solid #000; width: 120px;">Keterangan Tambahan:</p>
                    ' . $space . '
                '
                );

                foreach ($dataHead->keterangan_tambahan as $el) {
                    $pdf->writeHTML('
                        <p style="font-size:10px;">- ' . $el . '</p>
                    ');
                }
            }

            // $pdf->writeHTML('
            //         </div>
            //     </td>
            //     <td style="border: 1px solid; font-size: 10px; padding: 3px;" colspan="2"><b>SUB TOTAL</b></td>
            //     <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($sub_total) . '</td></tr>
            // ');

            $pdf->writeHTML('
                    </div>
                </td>
                <td colspan="3" style="border:none; padding:0; margin:0; vertical-align:top;">
                    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr >
                            <td style="width:60%; border:1px solid #000; font-size:10px; padding:3px;">
                                <b>SUB TOTAL</b>
                            </td>

                            <td style="width:33%; border:1px solid #000; font-size:9px; text-align:center;">
                                ' . self::rupiah($sub_total) . '
                            </td>
                        </tr>

                
            ');

            if ($diskon != 0 && $diskon != null) {

                $pdf->writeHTML('
                    <tr >
                    <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>DISKON</b></span><br><span style="font-size: 7px;">(*Total Diskon)</span></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($diskon) . '</td></tr>
                ');

                $pdf->writeHTML('
                    <tr >
                    <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>TOTAL SETELAH DISKON</b></span></td>
                    <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon) . '</td></tr>
                ');
            }

            if ($ppn != 0 && $ppn != null) {

                $pdf->writeHTML('
                    <tr >
                    <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>PPN</b></span><br><span style="font-size: 7px;">(*Total PPN)</span></td>
                    <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($ppn) . '</td></tr>
                ');

                // cek ada pph atau tidak
                if ($pph != 0 && $pph != null) {

                    $pdf->writeHTML('
                        <tr >
                        <td style="border: 1px solid; padding:3px;" width:60%;><span style="font-size: 10px;"><b>PPH</b></span><br><span style="font-size: 7px;">(*Total PPH)</span></td>
                        <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($pph) . '</td></tr>
                    ');

                    // cek apakah ada biaya diluar pajak atau tidak
                    if ($pajak == 0) {
                        // cek ada diskon atau tidak
                        if ($diskon != 0 && $diskon != null) {
                            $pdf->writeHTML('
                                <tr >
                                <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon + $ppn - $pph) . '</td></tr>
                            ');
                        } else {
                            $pdf->writeHTML('
                                <tr >
                                <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total + $ppn - $pph) . '</td></tr>
                            ');
                        }

                        foreach ($harga1 as $detailPajak) {

                            $al = json_decode(json_encode($detailPajak));

                            //cek apakah ada biaya diluar pajak
                            if (isset($al->biaya_di_luar_pajak)) {
                                $detailBiayaPajak = json_decode($al->biaya_di_luar_pajak);
                                if ($detailBiayaPajak->select != []) {
                                    foreach ($detailBiayaPajak->select as $vp) {
                                        $pdf->writeHTML('
                                            <tr >
                                            <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;">' . $vp->deskripsi . '</span></td>
                                            <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($vp->harga) . '</td></tr>
                                        ');
                                    }
                                }
                            }
                        };
                    }
                } else {
                    // cek apakah ada biaya diluar pajak atau tidak
                    if ($pajak == 0) {

                        // cek ada diskon atau tidak
                        if ($diskon != 0 && $diskon != null) {

                            $pdf->writeHTML('
                                <tr >
                                <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon + $ppn) . '</td></tr>
                            ');
                        } else {

                            $pdf->writeHTML('
                                <tr >
                                <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total + $ppn) . '</td></tr>
                            ');
                        }

                        foreach ($harga1 as $detailPajak) {

                            $al = json_decode(json_encode($detailPajak));
                            //cek apakah ada biaya diluar pajak
                            if (isset($al->biaya_di_luar_pajak)) {
                                $detailBiayaPajak = json_decode($al->biaya_di_luar_pajak);
                                if ($detailBiayaPajak->select != []) {
                                    foreach ($detailBiayaPajak->select as $vp) {
                                        $pdf->writeHTML('
                                            <tr >
                                            <td style="border: 1px solid; padding: 3px;" width:60%;><span style="font-size: 10px;">' . $vp->deskripsi . '</span></td>
                                            <td style="border: 1px solid; width:33%; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($vp->harga) . '</td></tr>
                                        ');
                                    }
                                }
                            }
                        };
                    }
                }
            }


            $pdf->writeHTML('
                <tr >
                <td style="border: 1px solid; font-size: 10px; padding:3px;" width:60%;><span><b>TOTAL</b></span></td>
                <td style="border: 1px solid; font-size: 9px; width:33%; text-align:center;" class="text-right">' . self::rupiah($total_harga) . '</td></tr>
            ');

            $ketDetail = json_decode(json_encode($harga1[0]));

            if ($ketDetail->keterangan == null) {
                $ket = '-';
            } else {
                $ket = $ketDetail->keterangan;
            }

            $pdf->writeHTML('
                <tr><td  style="height: 10px;"></td></tr>
                <tr >
                <td style="border: 1px solid; font-size: 10px; padding: 3px;" width:60%;><b style="text-transform: uppercase;">' . $ket . '</b></td>
                <td style="border: 1px solid; font-size: 9px; width:33%; text-align:center;" class="text-right">' . self::rupiah($nilai_tagihan) . '</td></tr>
            ');
            // dd($sisa_tagihan);
            // dd($total_tagihan, $nilai_tagihan);
            $sisa_tagihan = $total_tagihan - $nilai_tagihan;
            if (abs($sisa_tagihan) > 10) {
                $pdf->writeHTML('
                    <tr >
                    <td style="border: 1px solid; font-size: 10px; padding:3px;" width:60%;><b style="text-transform: uppercase;">SISA PEMBAYARAN</b></td>
                    <td style="border: 1px solid; font-size: 9px; width:33%; text-align:center;" class="text-right">' . self::rupiah($sisa_tagihan) . '</td></tr>
                ');
            }


            $pdf->writeHTML('
                   
                    </table>
                </td>

                
            ');

            $pdf->writeHTML('
                    </tbody>
                </table>
            ');

            $pdf->writeHTML('
                <table style="margin-top: 30px;" width="100%">
                    <tr>
                        <td style="padding-right:50px;">
                        </td>
            ');

            $qr_img = '';
            $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
            $qr = DB::table('qr_documents')->where('file', $qr_name)->where('type_document', 'invoice')->first();
            if ($qr) {
                 if($nilai_tagihan > 4999999){
                     $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
                 } else {
                     $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>';
                 }
            }
            if($nilai_tagihan > 4999999){
                $pdf->writeHTML('
                            <td width="25%" style="text-align:center;">
                                <div style="float: right; text-align: center;">
                                    <span style="font-size: 10px;">' . $area . ', ' . self::tanggal_indonesia($dataHead->tgl_invoice) . '</span><br><br><br><br><br><br><br>
                                    <span style="border-bottom: solid 1px #000; font-size:10px;"><b>' . $dataHead->nama_pj . '</b></span><br>
                                    <span style="font-size:10px;">' . $dataHead->jabatan_pj . '</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                ');
            } else {
                $pdf->writeHTML('
                        <td width="25%" style="text-align:center;">
                            <div style="float: right; text-align: center;">
                                <span style="font-size: 10px;">' . $area . ', ' . self::tanggal_indonesia($dataHead->tgl_invoice) . '</span><br><br>
                                <span>' . $qr_img . '</span><br>
                            </div>
                        </td>
                    </tr>
                </table>
            ');
            }

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
                        'content' =>  $nilai_tagihan > 4999999 ? '' . $qr_img . '' : '',
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

            $filePath = public_path('invoice/' . $fileName);
            $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
            return $fileName;
        } catch (\Exception $e) {
            dd($e);
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

    static function renderCustom($noInvoice)
    {
        try {

            $dataHead = Invoice::where('is_active', true)
                ->where('no_invoice', $noInvoice)
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

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            $pdf->showWatermarkText = true;
            $area = '';

            $customInvoice = json_decode($dataHead->custom_invoice);
            if ($customInvoice->data[0]->id_cabang == 1) {
                $area = 'Tangerang';
            } elseif ($customInvoice->data[0]->id_cabang == 4) {
                $area = 'Karawang';
            } elseif ($customInvoice->data[0]->id_cabang == 5) {
                $area = 'Pemalang';
            }

            $pr = '';
            if ($dataHead->periode != null) {
                $pr = self::tanggal_indonesia($dataHead->periode, 'period');
            } else {
                $pr = "";
            }

            $konsultant = $dataHead->nama_perusahaan;
            $jab_pic = '';

            $fileName = 'INVOICE' . '_' . preg_replace('/\\//', '_', $dataHead->no_invoice) . '.pdf';
            $jab_pic = $customInvoice->data[0]->jabatan_pic;

            // $qr_img = '';
            // if ($dataHead->is_generate == 1) {
            //     $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
            //     $qr = DB::table('qr_documents')->where('file', $qr_name)->where('type_document', 'invoice')->first();
            //     if ($qr) $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
            // }

            // $footer = array(
            //     'odd' => array(
            //         'C' => array(
            //             'content' => 'Hal {PAGENO} dari {nbpg}',
            //             'font-size' => 6,
            //             'font-style' => 'I',
            //             'font-family' => 'serif',
            //             'color' => '#606060'
            //         ),
            //         'R' => array(
            //             'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
            //             'font-size' => 5,
            //             'font-style' => 'I',
            //             // 'font-style' => 'B',
            //             'font-family' => 'serif',
            //             'color' => '#000000'
            //         ),
            //         'L' => array(
            //             'content' => '' . $qr_img . '',
            //             'font-size' => 4,
            //             'font-style' => 'I',
            //             // 'font-style' => 'B',
            //             'font-family' => 'serif',
            //             'color' => '#000000'
            //         ),
            //         'line' => -1,
            //     )
            // );

            // $pdf->setFooter($footer);

            $trAlamat = '<tr>
                <td style="width:35%;"><p style="font-size: 10px;"><u>Alamat Kantor :</u><br><span
                id="alamat_kantor" style="white-space: pre-wrap; word-wrap: break-word;">' . $dataHead->alamat_penagihan . '</span><br><span id="no_tlp_perusahaan">' . $customInvoice->data[0]->no_tlp_perusahaan . '</span><br><span
                id="nama_pic_order">' . $dataHead->nama_pic . $jab_pic . ' - ' . $dataHead->no_pic . '</span><br><span id="email_pic_order">' . $dataHead->email_pic . '</span></p></td>
                <td style="width: 30%; text-align: center;"></td>
                </tr>';

            $pdf->SetHTMLHeader('
                <table class="tabel">
                <tr class="tr_top">
                <td class="text-left text-wrap" style="width: 33.33%;"><img class="img_0"
                src="' . public_path() . '/img/isl_logo.png" alt="ISL">
                </td>
                <td style="width: 33.33%; text-align: center;">
                <h5 style="text-align:center; font-size:10px;"><b><u>INVOICE</u></b></h5>
                <p style="font-size: 10px;text-align:center;margin-top: -10px;">' . $dataHead->no_invoice . '
                </p>
                </td>
                <td style="text-align: right;">
                <p style="font-size: 8px; text-align:right;"><b>PT INTI SURYA
                LABORATORIUM</b><br><span
                style="white-space: pre-wrap; word-wrap: break-word;">Ruko Icon Business Park blok O no 5 - 6, BSD City
                Jl. Raya Cisauk, Sampora, Cisauk, Kab. Tangerang</span><br><span>T : 021-50898988/89 - sales@intilab.com</span><br>www.intilab.com
                </p>
                </td>
                </tr>
                </table>
                <table class="head2" width="100%">
                <tr>
                <td colspan="3"><h6 style="font-size:10px; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $dataHead->nama_perusahaan . '</h6></td>
                </tr>
                ' . $trAlamat . '
                </table>
                ');

            $pdf->writeHTML('
                <table style="width:100%;">
                <tr>
                <th></th>
                ');

            if ($dataHead->faktur_pajak != null && $dataHead->faktur_pajak != "") {
                $pdf->writeHTML('
                            <th style="text-align:left;padding:5px;font-size:10px;"><b>Faktur Pajak: ' . $dataHead->faktur_pajak . '</b></th> 
                            ');
            }

            if ($dataHead->no_po != null && $dataHead->no_po != "") {
                $pdf->writeHTML('
                            <th style="text-align:right;padding:5px;font-size:10px;"><b>No. PO: ' . $dataHead->no_po . '</b></th>  
                            ');
            }

            $pdf->writeHTML('
                        </tr>
                        </table>
                        ');

            $pdf->writeHTML('
                        <table style="border-collapse: collapse;">
                        <thead>
                        <tr>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">NO</th>
                        <th style="font-size:10px; padding:14px; padding:5px;border:1px solid #000">NO QT</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;" class="text-center" colspan="3">KETERANGAN PENGUJIAN</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">TITIK</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">HARGA SATUAN</th>
                        <th style="font-size:10px; padding:14px; border:1px solid #000;">TOTAL HARGA</th>
                        </tr>
                        </thead>
                        <tbody>
                        ');

            $no = 1;

            foreach ($customInvoice->data as $k => $invoice) {
                // Debugging the invoice details

                $pdf->writeHTML(
                    '<tr style="border: 1px solid; font-size: 9px;">
                                    <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . (count($invoice->invoiceDetails) + 1) . '">' . ($k + 1) . '</td>
                                    <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . (count($invoice->invoiceDetails) + 1) . '">
                                    <span><b>' . $invoice->no_order . '</b></span><br>
                                    <span><b>' . $invoice->no_document . '</b></span><br>
                                    <span><b>' . $pr . '</b></span>
                                    </td>
                                    </tr>'
                );

                foreach ($invoice->invoiceDetails as $k => $itemInvoice) {
                    // Handle empty values
                    $titk = !empty($itemInvoice->titk) ? $itemInvoice->titk : ' ';  // Default to 'N/A' if empty
                    $keterangan = !empty($itemInvoice->keterangan) ? $itemInvoice->keterangan : 'No Description'; // Default text if empty
                    $hargaSatuan = !empty($itemInvoice->harga_satuan) ? self::rupiah($itemInvoice->harga_satuan) : ''; // Default to '0' if empty
                    $totalHarga = !empty($itemInvoice->total_harga) ? self::rupiah($itemInvoice->total_harga) : '0'; // Default to '0' if empty

                    $pdf->writeHTML('
                                <tr>
                                <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $keterangan . '</span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $titk . '</td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $hargaSatuan . '</td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $totalHarga . '</td>
                                </tr>
                                ');
                }
            }

            $spk = '';

            if ($dataHead->no_spk != null && $dataHead->no_faktur != null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . ' - No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_faktur != null && $dataHead->no_spk == null) {
                $spk = '<span style="font-size:10px">- No. Faktur: ' . $dataHead->no_faktur . '</span>';
            } else if ($dataHead->no_spk != null && $dataHead->no_faktur == null) {
                $spk = '<span style="font-size:10px">- No. SPK: ' . $dataHead->no_spk . '</span>';
            } else {
                $spk = '';
            }

            $space = '<p style="font-size:4px;">&nbsp;</p>';
            $spaceSection = '<p style="font-size:8px;">&nbsp;</p>';

            $pdf->writeHTML('
                <tr>
                    <td style="height: 7px;" colspan="5"></td>
                </tr>
                <tr class="line_">
                <td rowspan="11" colspan="5">
                    <div>   
                    </div>
                        <p style="line-height:normal; font-size:9px;">Terbilang: </p>
                        ' . $space . '
                        <p>
                            <b style="font-size:9px; text-align:center; white-space:normal; line-height:normal;">' . self::terbilang($customInvoice->harga->nilai_tagihan != null ? $customInvoice->harga->nilai_tagihan : $customInvoice->harga->total_harga) . ' Rupiah</b>
                        </p>
                        ' . $spaceSection . '
                        <p style="font-size:10px; border-bottom:1px solid #000; width: 120px; padding-bottom:5px;">Keterangan Pembayaran:</p>
                        ' . $space . '
                        <p style="font-size:10px;">- Pembayaran dilakukan secara <b style="font-style: italic;">"Full Amount"</b> (tanpa pemotongan biaya apapun)</p>
                        <p style="font-size:10px;">- <b>Cash / Transfer : ' . $dataHead->rekening . ' atas nama PT Inti Surya Laboratorium Bank Central Asia (BCA) - Kota Tangerang - Cabang BSD Serpong</b></p>
                        <p style="font-size:10px">- Pembayaran baru dianggap sah apabila cek / giro telah dapat dicairkan</p>
                        <p style="font-size:10px">- Bukti Pembayaran agar dapat di e-mail ke : billing@intilab.com</p>
                        <p style="font-size:10px">- Invoice asli ini berlaku juga sebagai kwitansi asli yang sah</p>
                        ' . $spk . '
                ');

            if ($dataHead->keterangan_tambahan != null && count($dataHead->keterangan_tambahan) > 0) {

                $pdf->writeHTML(
                    $spaceSection . '
                    <p style="font-size:10px; border-bottom:1px solid #000; width: 120px;">Keterangan Tambahan:</p>
                    ' . $space . '
                '
                );

                foreach ($dataHead->keterangan_tambahan as $el) {
                    $pdf->writeHTML('
                        <p style="font-size:10px;">- ' . $el . '</p>
                    ');
                }
            }

            $pdf->writeHTML('
                    </div>
                </td>
                <td colspan="3" style="border:none; padding:0; margin:0; vertical-align:top;">
                    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                        <tr >
                            <td style="width:60%; border:1px solid #000; font-size:10px; padding:3px;" colspan="7">
                                <b>SUB TOTAL</b>
                            </td>

                            <td style="width:33%; border:1px solid #000; font-size:9px; text-align:center;" colspan="2">
                                ' . self::rupiah($customInvoice->harga->total_custom) . '
                            </td>
                        </tr>
            ');

            if ($customInvoice->harga->total_diskon != 0 && $customInvoice->harga->total_diskon != null) {

                $pdf->writeHTML('
                                <tr>
                                    <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>DISKON</b></span><br><span style="font-size: 7px;">(*Total Diskon)</span></td>
                                    <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_diskon) . '</td>
                                </tr>
                                ');

                $pdf->writeHTML('
                                <tr>
                                    <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>TOTAL SETELAH DISKON</b></span></td>
                                    <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_custom - $customInvoice->harga->total_diskon) . '</td>
                                </tr>
                                ');
            }
            if ($customInvoice->harga->total_ppn != 0 && $customInvoice->harga->total_ppn != null) {
                $pdf->writeHTML('
                                <tr>
                                <td style="width:60%; border: 1px solid; padding: 3px;" colspan="7"><span style="font-size: 10px;"><b>PPN</b></span><br><span style="font-size: 7px;">(*Total PPN)</span></td>
                                <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_ppn) . '</td></tr>
                                ');
            }
            if ($customInvoice->harga->total_pph != 0 && $customInvoice->harga->total_pph != null) {
                $pdf->writeHTML('
                            <tr>
                            <td style="width:60%; border: 1px solid; padding:3px;" colspan="7"><span style="font-size: 10px;"><b>PPH</b></span><br><span style="font-size: 7px;">(*Total PPH)</span></td>
                            <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_pph) . '</td></tr>
                            ');
            }
            $pdf->writeHTML('
                        <tr>
                        <td style="width:60%; border: 1px solid; font-size: 10px; padding:3px;" colspan="7"><span><b>TOTAL</b></span></td>
                        <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->total_custom - $customInvoice->harga->total_diskon - $customInvoice->harga->total_pph + $customInvoice->harga->total_ppn) . '</td></tr>
                        ');
            $pdf->writeHTML('
                        <tr><td colspan="5" style="height: 10px;"></td></tr>
                        <tr>
                        <td style="width:60%; border: 1px solid; font-size: 10px; padding: 3px;" colspan="7"><b style="text-transform: uppercase;">' . $dataHead->keterangan . '</b></td>
                        <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->nilai_tagihan) . '</td></tr>
                        ');
            if (abs($customInvoice->harga->sisa_tagihan) > 10) {
                $pdf->writeHTML('
                            <tr>
                            <td style="width:60%; border: 1px solid; font-size: 10px; padding:3px;" colspan="7"><b style="text-transform: uppercase;">SISA PEMBAYARAN</b></td>
                            <td style="width:33%; border: 1px solid; font-size: 9px; text-align:center;" colspan="2" class="text-right">' . self::rupiah($customInvoice->harga->sisa_tagihan) . '</td></tr>
                            ');
            }
            $pdf->writeHTML('
                    </table>
                </td> 
            ');
            $pdf->writeHTML('
                    </tbody>
                </table>
            ');

            $pdf->writeHTML('
                <table style="margin-top: 30px;" width="100%">
                    <tr>
                        <td style="padding-right:50px;">
                        </td>
            ');

            $qr_img = '';
            $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
            $qr = DB::table('qr_documents')->where('file', $qr_name)->where('type_document', 'invoice')->first();
            if ($qr) {
                 if($customInvoice->harga->nilai_tagihan > 4999999){
                     $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
                 } else {
                     $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>';
                 }
            }
            if($customInvoice->harga->nilai_tagihan > 4999999){
                $pdf->writeHTML('
                            <td width="25%" style="text-align:center;">
                                <div style="float: right; text-align: center;">
                                    <span style="font-size: 10px;">' . $area . ', ' . self::tanggal_indonesia($dataHead->tgl_invoice) . '</span><br><br><br><br><br><br><br>
                                    <span style="border-bottom: solid 1px #000; font-size:10px;"><b>' . $dataHead->nama_pj . '</b></span><br>
                                    <span style="font-size:10px;">' . $dataHead->jabatan_pj . '</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                ');
            } else {
                $pdf->writeHTML('
                        <td width="25%" style="text-align:center;">
                            <div style="float: right; text-align: center;">
                                <span style="font-size: 10px;">' . $area . ', ' . self::tanggal_indonesia($dataHead->tgl_invoice) . '</span><br><br>
                                <span>' . $qr_img . '</span><br>
                            </div>
                        </td>
                    </tr>
                </table>
            ');
            }

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
                        'content' =>  $customInvoice->harga->nilai_tagihan > 4999999 ? '' . $qr_img . '' : '',
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

            $filePath = public_path('invoice/' . $fileName);
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

    protected static function terbilang($angka)
    {
        $satuan = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
        $hasil = "";

        if ($angka < 12) {
            $hasil = $satuan[$angka];
        } elseif ($angka < 20) {
            $hasil = $satuan[$angka - 10] . " Belas";
        } elseif ($angka < 100) {
            $hasil = $satuan[floor($angka / 10)] . " Puluh " . $satuan[$angka % 10];
        } elseif ($angka < 200) {
            $hasil = "Seratus " . self::terbilang($angka - 100); // Ganti $this dengan self
        } elseif ($angka < 1000) {
            $hasil = $satuan[floor($angka / 100)] . " Ratus " . self::terbilang($angka % 100); // Ganti $this dengan self
        } elseif ($angka < 2000) {
            $hasil = "Seribu " . self::terbilang($angka - 1000); // Ganti $this dengan self
        } elseif ($angka < 1000000) {
            $hasil = self::terbilang(floor($angka / 1000)) . " Ribu " . self::terbilang($angka % 1000); // Ganti $this dengan self
        } elseif ($angka < 1000000000) {
            $hasil = self::terbilang(floor($angka / 1000000)) . " Juta " . self::terbilang($angka % 1000000); // Ganti $this dengan self
        }

        return trim($hasil);
    }


    protected static function tanggal_indonesia($tanggal, $mode = null)
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

    protected static function rupiah($angka)
    {
        $hasil_rupiah = "Rp " . number_format($angka, 0, ".", ",");
        return $hasil_rupiah;
    }

    private static function chunkByContentHeight($data, $extra_row = null)
    {
        $maxHeight = 1000;
        $chunks = [];
        $currentChunk = [];
        $currentHeight = 0;

        foreach ($data as $key => $row) {
            // Estimasi tinggi berdasarkan panjang teks
            $textLength = strlen($row->keterangan_pengujian ?? '') +
                strlen(json_encode($row->regulasi ?? ''));
            $rowHeight = 40 + ceil($textLength / 120) * 20;

            if ($key == count($data) - 1 && $extra_row != null) {
                $extraHeight = (40 + 20) * 3;
                if ($currentHeight + $rowHeight + $extraHeight > $maxHeight) {
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentHeight = 0;
                }
            } else {
                if ($currentHeight + $rowHeight > $maxHeight) {
                    $chunks[] = $currentChunk;
                    $currentChunk = [];
                    $currentHeight = 0;
                }
            }

            $currentChunk[] = $row;
            $currentHeight += $rowHeight;
        }

        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }
}
