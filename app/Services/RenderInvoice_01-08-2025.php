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
use Mpdf\Mpdf;

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
            if($invoice->is_custom == true) {
                $filename = $this->renderCustom($noInvoice);
            }else {
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

                    $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot_h.no_document', 'quot_h.wilayah', 'quot_d.data_pendukung_sampling', 'quot_d.transportasi', 'quot_d.harga_transportasi_total', 'quot_d.harga_transportasi', 'quot_d.jumlah_orang_24jam AS jam_jumlah_orang_24', 'quot_d.harga_24jam_personil_total', 'quot_d.total_biaya_lain', 'quot_d.perdiem_jumlah_orang', 'quot_d.harga_perdiem_personil_total', 'quot_d.biaya_lain', 'quot_d.grand_total', 'quot_d.discount_air', 'quot_d.total_discount_air', 'quot_d.discount_non_air', 'quot_d.total_discount_non_air', 'quot_d.discount_udara', 'quot_d.total_discount_udara', 'quot_d.discount_emisi', 'quot_d.total_discount_emisi', 'quot_d.discount_transport', 'quot_d.total_discount_transport', 'quot_d.discount_perdiem', 'quot_d.total_discount_perdiem', 'quot_d.discount_perdiem_24jam', 'quot_d.total_discount_perdiem_24jam', 'quot_d.discount_gabungan', 'quot_d.total_discount_gabungan', 'quot_d.discount_consultant', 'quot_d.total_discount_consultant', 'quot_d.discount_group', 'quot_d.total_discount_group', 'quot_d.cash_discount_persen', 'quot_d.total_cash_discount_persen', 'quot_d.cash_discount', 'quot_d.custom_discount', 'quot_h.syarat_ketentuan', 'quot_h.keterangan_tambahan', 'quot_d.total_dpp', 'quot_d.total_ppn', 'quot_d.total_pph', 'quot_d.pph', 'quot_d.total_biaya_di_luar_pajak', 'quot_d.piutang', 'quot_d.biaya_akhir', 'quot_h.is_active', 'quot_h.id_cabang', 'quot_d.biaya_preparasi', 'quot_d.total_biaya_preparasi')
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

                    $hargaDetail = Invoice::select(DB::raw('SUM(quot_d.total_discount) AS diskon, SUM(quot_d.total_dpp) AS total_dpp, SUM(quot_d.total_ppn) AS ppn, SUM(quot_d.grand_total) AS sub_total, SUM(quot_d.total_pph) AS pph, SUM(quot_d.biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(invoice.piutang) AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_d.total_discount_transport, quot_d.biaya_di_luar_pajak, quot_d.total_discount_perdiem'))
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
                    $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot.*')
                        ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                        ->leftJoin(DB::raw('(SELECT no_document, wilayah, data_pendukung_sampling, transportasi, harga_transportasi_total, harga_transportasi, jumlah_orang_24jam AS jam_jumlah_orang_24, harga_24jam_personil_total, total_biaya_lain, perdiem_jumlah_orang, harga_perdiem_personil_total, total_biaya_lain AS biaya_lain, total_biaya_preparasi AS biaya_preparasi_padatan, grand_total, discount_air, total_discount_air, discount_non_air, total_discount_non_air, discount_udara, total_discount_udara, discount_emisi, total_discount_emisi, discount_transport, total_discount_transport, discount_perdiem, total_discount_perdiem, discount_perdiem_24jam, total_discount_perdiem_24jam, discount_gabungan, total_discount_gabungan, discount_consultant, total_discount_consultant, discount_group, total_discount_group, cash_discount_persen, total_cash_discount_persen, cash_discount, custom_discount, syarat_ketentuan, keterangan_tambahan, total_dpp, total_ppn, total_pph, pph, total_biaya_di_luar_pajak, piutang, biaya_akhir, is_active, total_biaya_preparasi AS biaya_preparasi FROM request_quotation_kontrak_H) AS quot'), 'invoice.no_quotation', '=', 'quot.no_document')
                        ->where('no_invoice', $noInvoice)
                        ->where('quot.is_active', true)
                        ->where('invoice.is_active', true)
                        ->where('invoice.no_quotation', $value->no_quotation)
                        ->orderBy('invoice.no_order')
                        ->first();

                    $hargaDetail = Invoice::select(DB::raw('quot_h.total_discount AS diskon, quot_h.total_ppn AS ppn, SUM(quot_h.total_dpp) AS total_dpp, quot_h.grand_total AS sub_total, quot_h.total_pph AS pph, quot_h.biaya_akhir AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, invoice.piutang AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_h.total_discount_transport, quot_h.biaya_diluar_pajak AS biaya_di_luar_pajak, quot_h.total_discount_perdiem'))
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
                $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot.*')
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->leftJoin(DB::raw('(SELECT no_document, wilayah, data_pendukung_sampling, transportasi, harga_transportasi_total, harga_transportasi, jumlah_orang_24jam AS jam_jumlah_orang_24, harga_24jam_personil_total, total_biaya_lain, perdiem_jumlah_orang, harga_perdiem_personil_total, total_biaya_lain AS biaya_lain, biaya_lain AS keterangan_biaya, biaya_preparasi_padatan, grand_total, discount_air, total_discount_air, discount_non_air, total_discount_non_air, discount_udara, total_discount_udara, discount_emisi, total_discount_emisi, discount_transport, total_discount_transport, discount_perdiem, total_discount_perdiem, discount_perdiem_24jam, total_discount_perdiem_24jam, discount_gabungan, total_discount_gabungan, discount_consultant, total_discount_consultant, discount_group, total_discount_group, cash_discount_persen, total_cash_discount_persen, cash_discount, custom_discount, syarat_ketentuan, keterangan_tambahan, total_dpp, total_ppn, total_pph, pph, biaya_di_luar_pajak, total_biaya_di_luar_pajak, piutang, biaya_akhir, is_active, id_cabang, biaya_preparasi_padatan AS biaya_preparasi FROM request_quotation) AS quot'), 'invoice.no_quotation', '=', 'quot.no_document')
                ->where('no_invoice', $noInvoice)
                ->where('invoice.no_quotation', $value->no_quotation)
                ->where('quot.is_active', true)
                ->where('invoice.is_active', true)
                ->orderBy('invoice.no_order')
                ->first();

                $hargaDetail = Invoice::select(DB::raw('SUM(total_discount) AS diskon, SUM(total_ppn) AS ppn, SUM(grand_total) AS sub_total, SUM(total_dpp) AS total_dpp, SUM(total_pph) AS pph, SUM(biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(piutang) AS sisa_tagihan, keterangan, SUM(invoice.total_tagihan) AS total_tagihan, total_discount_transport, biaya_di_luar_pajak, total_discount_perdiem'))
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

        // dd($data1, $harga1);

            $mpdfConfig = array(
                'mode' => 'utf-8',
                'format' => 'A4',
                'keep_table_proportions' => true,
                'margin_header' => 3, // 30mm not pixel
                'margin_bottom' => 3, // 30mm not pixel
                'margin_footer' => 3,
                'use_kwt' => true,
                'setAutoTopMargin' => 'stretch',
                'setAutoBottomMargin' => 'stretch',
                'orientation' => 'P'
            );

            $pdf = new Mpdf($mpdfConfig);
            $pdf->SetProtection(array('print'), '', 'skyhwk12');
            $pdf->SetWatermarkImage(public_path() . '/logo-watermark.png', -1, '', array(65, 60));
            $pdf->showWatermarkImage = true;
            $pdf->showWatermarkText = true;
            $css = "
                <style>
                    table {
                        border-collapse: collapse;
                        width: 100%;
                    }
                    tr {
                        page-break-inside: avoid;
                    }
                    td[rowspan] {
                        page-break-inside: avoid;
                    }
                </style>
            ";
            $pdf->writeHTML($css, 1); // Tambahkan CSS ke dalam mPDF


            $konsultant = '';
            $jab_pic = '';

            $data = json_decode(json_encode($data1[0]));
            // dd($data);
            if($data == null){
                $area = 'Tangerang';
            } else {
                if ($data->id_cabang == 1)
                    $area = 'Tangerang';
                if ($data->id_cabang == 4)
                    $area = 'Karawang';
                if ($data->id_cabang == 5)
                    $area = 'Pemalang';

                if ($data->konsultan != null || $data->konsultan != '') {
                    if($data->no_invoice == 'ISL/INV/2502907'){
                        $perusahaan = '';
                    } else{
                        $perusahaan = '(' . $data->nama_perusahaan . ')';
                    }
                    $konsultant = $data->konsultan;
                } else {
                    $perusahaan = $data->nama_perusahaan;
                }
            }



            // $strReplace = Helpers::escapeStr('INVOICE_' . $dataHead->no_invoice . '_' . $konsultant);
            $fileName = 'INVOICE'. '_' . preg_replace('/\\//', '_', $dataHead->no_invoice) . '.pdf';
            if ($dataHead->jabatan_pic != '')
                $jab_pic = ' (' . $dataHead->jabatan_pic . ')';

            $qr_img = '';
            if($dataHead->is_generate == 1){
                $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
            $qr = DB::table('qr_documents')->where('file' , $qr_name )->where('type_document', 'invoice')->first();
            if ($qr) $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
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
                        <td colspan="3"><h6 style="font-size:10px; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . $perusahaan . '</h6></td>
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
            // dd($data1);
            $data_pdf = [];
            foreach ($data1 as $k => $valSampling) {
                $values = json_decode(json_encode($valSampling));
                $cekArray = json_decode($values->data_pendukung_sampling);
                // dd($cekArray);

                if ($cekArray == []) {
                    // dd('atas',$values);
                    $entry = [
                        'no_order' => $values->no_order,
                        'no_document' => $values->no_document,
                        'periode' => $values->periode,
                        'details' => []
                    ];

                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                        $ket_transportasi = isset($values->keterangan_transportasi)
                            ? $values->keterangan_transportasi
                            : "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];

                        $entry['details'][] = [
                            'keterangan' => $ket_transportasi,
                            'titik' => $values->transportasi,
                            'harga_satuan' => self::rupiah($values->harga_transportasi),
                            'total_harga' => self::rupiah($values->harga_transportasi_total)
                        ];
                    }

                    if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                        $perdiem_24 = "";
                                $total_perdiem = 0;
                        if  ( $values->jam_jumlah_orang_24 > 0 &&
                                $values->jam_jumlah_orang_24 != "" && $values->harga_24jam_personil_total != null
                                && !is_null($values->harga_24jam_personil_total)
                            ) {
                                $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                                $total_perdiem = $total_perdiem + $data->{'harga_24jam_personil_total'};
                            }
                        $entry['details'][] = [
                            'keterangan' => isset($values->keterangan_perdiem) ? $values->keterangan_perdiem : 'Perdiem ' . $perdiem_24,
                            'titik' => ' ',
                            'harga_satuan' => isset($values->satuan_perdiem) ? self::rupiah($values->satuan_perdiem ) : ' ',
                            'total_harga' => self::rupiah($values->harga_perdiem_personil_total + $total_perdiem)
                        ];
                    }

                    if (isset($values->keterangan_lainnya)) {
                        foreach (json_decode($values->keterangan_lainnya) as $ket) {
                            $entry['details'][] = [
                                'keterangan' => $ket->deskripsi,
                                'titik' => $ket->titik,
                                'harga_satuan' => self::rupiah($ket->harga_satuan),
                                'total_harga' => self::rupiah($ket->harga_total)
                            ];
                        }
                    }

                    if ($values->biaya_lain != null && $values->total_biaya_lain > 0) {
                        if (isset($values->keterangan_biaya_lain)) {
                            $biayaLainArray = is_array($values->keterangan_biaya_lain)
                                ? $values->keterangan_biaya_lain
                                : json_decode($values->keterangan_biaya_lain, true);

                            if (is_array($biayaLainArray)) {
                                foreach ($biayaLainArray as $biayaLain) {
                                    $entry['details'][] = [
                                        'keterangan' => $biayaLain['deskripsi'],
                                        'titik' => '',
                                        'harga_satuan' => '',
                                        'total_harga' => self::rupiah($biayaLain['harga'])
                                    ];
                                }
                            } else {
                                $entry['details'][] = [
                                    'keterangan' => 'Biaya Lain-Lain',
                                    'titik' => '',
                                    'harga_satuan' => '',
                                    'total_harga' => self::rupiah($values->biaya_lain)
                                ];
                            }
                        } else {
                            // fallback ke keterangan_biaya
                            $biayaLainArray = json_decode($values->keterangan_biaya, true);
                            if (is_array($biayaLainArray)) {
                                foreach ($biayaLainArray as $biayaLain) {
                                    $entry['details'][] = [
                                        'keterangan' => $biayaLain['deskripsi'],
                                        'titik' => '',
                                        'harga_satuan' => '',
                                        'total_harga' => self::rupiah($biayaLain['harga'])
                                    ];
                                }
                            } else {
                                $entry['details'][] = [
                                    'keterangan' => 'Biaya Lain-Lain',
                                    'titik' => '',
                                    'harga_satuan' => '',
                                    'total_harga' => self::rupiah($values->biaya_lain)
                                ];
                            }
                        }
                    }

                    $data_pdf[] = $entry;
                } else {
                    if (is_array($cekArray)) {
                        // dd('tengah',$cekArray);

                            $entry = [
                                'no_order' => $values->no_order,
                                'no_document' => $values->no_document,
                                'periode' => $values->periode,
                                'details' => []
                            ];

                            foreach ($cekArray as $dataSampling) {
                                $kategori2 = explode("-", $dataSampling->kategori_2);

                                $regulasi = is_array($dataSampling->regulasi) ? implode(", ", $dataSampling->regulasi) : $dataSampling->regulasi;

                                $entry['details'][] = [
                                    'keterangan' => isset($dataSampling->keterangan_pengujian)
                                        ? $dataSampling->keterangan_pengujian
                                        : strtoupper($kategori2[1]) . 
                                            ($dataSampling->penamaan_titik ? ' (' . $dataSampling->penamaan_titik . ')' : '') . 
                                            ' - ' . $dataSampling->total_parameter . 
                                            ' Parameter' . 
                                            ($regulasi ? ' ' . $regulasi : ''),
                                    'titik' => (isset($dataSampling->periode) && $dataSampling->periode)
                                        ? $dataSampling->jumlah_titik * count($dataSampling->periode)
                                        : $dataSampling->jumlah_titik,

                                    'harga_satuan' => self::rupiah($dataSampling->harga_satuan),

                                    'total_harga' => (isset($dataSampling->periode) && $dataSampling->periode)
                                        ? self::rupiah($dataSampling->harga_total * count($dataSampling->periode))
                                        : self::rupiah($dataSampling->harga_total)
                                ];

                            }

                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                $ket_transportasi = isset($values->keterangan_transportasi) ? $values->keterangan_transportasi : "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];

                                $entry['details'][] = [
                                    'keterangan' => $ket_transportasi,
                                    'titik' => $values->transportasi,
                                    'harga_satuan' => self::rupiah($values->harga_transportasi),
                                    'total_harga' => self::rupiah($values->harga_transportasi_total)
                                ];
                            }

                            if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                $perdiem_24 = "";
                                $total_perdiem = 0;
                                // dd($values);
                                if  ( $values->jam_jumlah_orang_24 > 0 &&
                                        $values->jam_jumlah_orang_24 != "" && $values->harga_24jam_personil_total != null
                                        && !is_null($values->harga_24jam_personil_total)
                                    ) {
                                        $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                                        $total_perdiem = $total_perdiem + $data->{'harga_24jam_personil_total'};
                                    }
                                $entry['details'][] = [
                                    'keterangan' => isset($values->keterangan_perdiem) ? $values->keterangan_perdiem : 'Perdiem ' . $perdiem_24,
                                    'titik' => ' ',
                                    'harga_satuan' => isset($values->satuan_perdiem) ? self::rupiah($values->satuan_perdiem) : ' ',
                                    'total_harga' => self::rupiah($values->harga_perdiem_personil_total + $total_perdiem)
                                ];
                            }

                            if (isset($values->keterangan_lainnya)) {
                                foreach (json_decode($values->keterangan_lainnya) as $ket) {
                                    $entry['details'][] = [
                                        'keterangan' => $ket->deskripsi,
                                        'titik' => $ket->titik,
                                        'harga_satuan' => self::rupiah($ket->harga_satuan),
                                        'total_harga' => self::rupiah($ket->harga_total)
                                    ];
                                }
                            }

                            if ($values->biaya_lain != null && $values->total_biaya_lain > 0) {

                                // Kode Lama
                                // $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                // if (is_array($biayaLainArray)) {
                                //     foreach ($biayaLainArray as $biayaLain) {
                                //         $entry['details'][] = [
                                //             'keterangan' => $biayaLain['deskripsi'],
                                //             'titik' => '',
                                //             'harga_satuan' => '',
                                //             'total_harga' => self::rupiah($biayaLain['harga'])
                                //         ];
                                //     }
                                // } else {
                                //     $entry['details'][] = [
                                //         'keterangan' => 'Biaya Lain-Lain',
                                //         'titik' => '',
                                //         'harga_satuan' => '',
                                //         'total_harga' => self::rupiah($values->biaya_lain)
                                //     ];
                                // }
                                if (!empty($values->biaya_lain) && $values->total_biaya_lain > 0) {
                                    // Cek dulu apakah properti keterangan_biaya ada dan valid
                                    if (isset($values->keterangan_biaya)) {
                                        $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                
                                        if (is_array($biayaLainArray)) {
                                            foreach ($biayaLainArray as $biayaLain) {
                                                $entry['details'][] = [
                                                    'keterangan' => $biayaLain['deskripsi'] ?? 'Biaya Lain',
                                                    'titik' => '',
                                                    'harga_satuan' => '',
                                                    'total_harga' => self::rupiah($biayaLain['harga'] ?? 0)
                                                ];
                                            }
                                        } else {
                                            // Kalau decoding gagal, fallback ke default
                                            $entry['details'][] = [
                                                'keterangan' => 'Biaya Lain-Lain',
                                                'titik' => '',
                                                'harga_satuan' => '',
                                                'total_harga' => self::rupiah($values->biaya_lain)
                                            ];
                                        }
                                    } else {
                                        // Fallback kalau property `keterangan_biaya` nggak ada
                                        $entry['details'][] = [
                                            'keterangan' => 'Biaya Lain-Lain',
                                            'titik' => '',
                                            'harga_satuan' => '',
                                            'total_harga' => self::rupiah($values->biaya_lain)
                                        ];
                                    }
                                }
                                
                            }

                            $data_pdf[] = $entry;

                    } else {
                        // dd('bawah',json_decode($values->data_pendukung_sampling));
                        foreach (json_decode($values->data_pendukung_sampling) as $dataSampling) {
                            $entry = [
                                'no_order' => $values->no_order,
                                'no_document' => $values->no_document,
                                'periode' => $values->periode,
                                'details' => []
                            ];

                            foreach ($dataSampling->data_sampling as $datasp) {
                                $kategori2 = explode('-', $datasp->kategori_2);
                                $regulasi = is_array($datasp->regulasi) ? implode(', ', $datasp->regulasi) : $datasp->regulasi;

                                $entry['details'][] = [
                                    'keterangan' => isset($datasp->keterangan_pengujian)
                                        ? $datasp->keterangan_pengujian
                                        : strtoupper($kategori2[1]). ($datasp->penamaan_titik ? '(' . $datasp->penamaan_titik . ')' : '') . ' - ' . $datasp->total_parameter . ' Parameter' . ($regulasi ? ' ' . $regulasi : ''),
                                    'titik' => $datasp->jumlah_titik,
                                    'harga_satuan' => self::rupiah($datasp->harga_satuan),
                                    'total_harga' => self::rupiah($datasp->harga_total)
                                ];
                            }

                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                $ket_transportasi = isset($values->keterangan_transportasi)
                                    ? $values->keterangan_transportasi
                                    : "Transportasi - Wilayah Sampling : " . explode('-', $values->wilayah)[1];

                                $entry['details'][] = [
                                    'keterangan' => $ket_transportasi,
                                    'titik' => $values->transportasi,
                                    'harga_satuan' => self::rupiah($values->harga_transportasi_total / $values->transportasi),
                                    'total_harga' => self::rupiah($values->harga_transportasi_total)
                                ];
                            }

                            if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                $perdiem_24 = "";
                                $total_perdiem = 0;
                                if  ( $values->jam_jumlah_orang_24 > 0 &&
                                        $values->jam_jumlah_orang_24 != "" && $values->harga_24jam_personil_total != null
                                        && !is_null($values->harga_24jam_personil_total)
                                    ) {
                                        $perdiem_24 = "Termasuk Perdiem (24 Jam)";
                                        $total_perdiem = $total_perdiem + $data->{'harga_24jam_personil_total'};
                                    }
                                $entry['details'][] = [
                                    'keterangan' => isset($values->keterangan_perdiem) ? $values->keterangan_perdiem : 'Perdiem ' . $perdiem_24,
                                    'titik' => ' ',
                                    'harga_satuan' => isset($values->satuan_perdiem) ? self::rupiah($values->satuan_perdiem) : ' ',
                                    'total_harga' => self::rupiah($values->harga_perdiem_personil_total + $total_perdiem)
                                ];
                            }

                            if (isset($values->keterangan_lainnya)) {
                                foreach (json_decode($values->keterangan_lainnya) as $ket) {
                                    $entry['details'][] = [
                                        'keterangan' => $ket->deskripsi,
                                        'titik' => $ket->titik,
                                        'harga_satuan' => self::rupiah($ket->harga_satuan),
                                        'total_harga' => self::rupiah($ket->harga_total)
                                    ];
                                }
                            }

                            if ($values->biaya_lain != null && $values->total_biaya_lain > 0) {
                                // dd($values);
                                foreach (json_decode($values->biaya_lain) as $biayaL) {
                                    $entry['details'][] = [
                                        'keterangan' => 'Biaya : ' . $biayaL->deskripsi,
                                        'titik' => isset($biayaL->qty) ? $biayaL->qty : '',
                                        'harga_satuan' => isset($biayaL->harga_satuan) ? self::rupiah($biayaL->harga_satuan) : '',
                                        'total_harga' => self::rupiah($biayaL->harga)
                                    ];
                                }
                            }

                            if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                $biayaPreparasi = json_decode($values->biaya_preparasi, true);
                                // dd($biayaPreparasi);
                                // $entry['details'][] = [
                                //     'keterangan' => $biayaPreparasi[0]->Deskripsi,
                                //     'titik' => '',
                                //     'harga_satuan' => self::rupiah($biayaPreparasi[0]->Harga),
                                //     'total_harga' => self::rupiah($values->total_biaya_preparasi)
                                // ];

                                // AKR
                                $entry['details'][] = [
                                    'keterangan' => $biayaPreparasi[5]['Deskripsi'],
                                    'titik' => '',
                                    'harga_satuan' => self::rupiah($biayaPreparasi[5]['Harga']),
                                    'total_harga' => self::rupiah($values->total_biaya_preparasi)
                                ];
                            }

                            $data_pdf[] = $entry;
                        }
                    }
                }

                $no++;

            }
            // dd($data_pdf);
            // Write To PDF
            $dataStructured = self::breakInvoiceDetailsNonCustom($data_pdf);
            $lastNoQT = '';
            $currentIndex = 1;
            foreach ($dataStructured as $k => $invoice) {
                $invoice = (object) $invoice;
                $printNoQT = $invoice->no_document != $lastNoQT;

                if ($printNoQT) {
                    $lastNoQT = $invoice->no_document;
                }
                // Debugging the invoice details
                $pdf->writeHTML(
                    '<tr style="border: 1px solid; font-size: 9px;">
                    <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . (count($invoice->details) + 1) . '">' . ($printNoQT ? $currentIndex++ : '') . '</td>
                        <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . (count($invoice->details) + 1) . '">
                        <span><b>' .($printNoQT ? $invoice->no_order : '') . '</b></span><br>
                        <span><b>' . ($printNoQT ?$invoice->no_document : '') . '</b></span>
                        <span><b>' . ($printNoQT ? ($invoice->periode ? self::tanggal_indonesia($invoice->periode, 'period') : '') : '') . '</b></span><br>
                        </td>
                        </tr>'
                    );

                    // Loop untuk menambahkan invoiceDetails
                    foreach ($invoice->details as $k => $itemInvoice) {
                        $itemInvoice = (object) $itemInvoice;
                    $pdf->writeHTML('
                    <tr>
                    <td style="border: 1px solid; font-size: 9px; padding:5px;" class="wrap" colspan="3"><span>' . $itemInvoice->keterangan . '</span></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-center">' . $itemInvoice->titik . '</td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $itemInvoice->harga_satuan . '</td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . $itemInvoice->total_harga . '</td>
                    </tr>
                    ');
                }
            }

            // dd('masuk');

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
            // dd($harga1);
            foreach ($harga1 as $detailHargaInvo) {

                $el = json_decode(json_encode($detailHargaInvo));
                // dd($el);
                //cek apakah ada biaya diluar pajak
                if (isset($el->biaya_di_luar_pajak)) {
                    $biayaDiLuarPajak = json_decode($el->biaya_di_luar_pajak);

                    if ($biayaDiLuarPajak->select != []) {
                    // dd('masuk');
                        $luarPajak = round($el->total_discount_transport) + round($el->total_discount_perdiem);
                        $totDisk =  $el->sub_total - $el->total_dpp;
                        $pajak = 0;
                    } else {
                        $totDisk = $el->sub_total - $el->total_dpp;
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
            // dd($diskon);

            $pdf->writeHTML('
            <tr><td style="height: 7px;" colspan="5"></td></tr>
            <tr class="line_">
            <td colspan="5"><span style="line-height:normal; font-size:9px;">Terbilang: </span><div><span><b style="font-size:9px; text-align:center; white-space:normal; line-height:normal;">' . self::terbilang($nilai_tagihan) . ' Rupiah</b></div></td>
            <td style="border: 1px solid; font-size: 10px; padding: 3px;" colspan="2"><b>SUB TOTAL</b></td>
            <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($sub_total) . '</td></tr>
            ');

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
            // dd('masuk');

            $pdf->writeHTML('
                <tr>
                    <td rowspan="10" colspan="5">
                        <span style="font-size:10px; border-bottom:1px solid #000; width: 120px; padding-bottom:5px;">Keterangan Pembayaran:</span><br><br>
                        <span style="font-size:10px;">- Pembayaran dilakukan secara <b style="font-style: italic;">"Full Amount"</b> (tanpa pemotongan biaya apapun)</span><br>
                        <span style="font-size:10px;">- <b>Cash / Transfer : ' . $dataHead->rekening . ' atas nama PT Inti Surya Laboratorium Bank Central Asia (BCA) - Kota Tangerang - Cabang BSD Serpong</b></span><br>
                        <span style="font-size:10px">- Pembayaran baru dianggap sah apabila cek / giro telah dapat dicairkan</span><br>
                        <span style="font-size:10px">- Bukti Pembayaran agar dapat di e-mail ke : billing@intilab.com</span><br>
                        <span style="font-size:10px">- Invoice asli ini berlaku juga sebagai kwitansi asli yang sah</span><br>
                        ' . $spk . '
                    </td>
                </tr>
            ');

            if ($diskon != 0 && $diskon != null) {

                $pdf->writeHTML('
                    <tr class="line_">
                    <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>DISKON</b></span><br><span style="font-size: 7px;">(*Total Diskon)</span></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($diskon) . '</td></tr>
                ');

                $pdf->writeHTML('
                    <tr class="line_">
                    <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH DISKON</b></span></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon) . '</td></tr>
                ');

            }

            if ($ppn != 0 && $ppn != null) {

                $pdf->writeHTML('
                    <tr class="line_">
                    <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>PPN</b></span><br><span style="font-size: 7px;">(*Total PPN)</span></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($ppn) . '</td></tr>
                ');

                // cek ada pph atau tidak
                if ($pph != 0 && $pph != null) {

                    $pdf->writeHTML('
                        <tr class="line_">
                        <td style="border: 1px solid; padding:3px;" colspan="2"><span style="font-size: 10px;"><b>PPH</b></span><br><span style="font-size: 7px;">(*Total PPH)</span></td>
                        <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($pph) . '</td></tr>
                    ');

                    // cek apakah ada biaya diluar pajak atau tidak
                    if ($pajak == 0) {
                        // cek ada diskon atau tidak
                        if ($diskon != 0 && $diskon != null) {
                            $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon + $ppn - $pph) . '</td></tr>
                            ');
                        } else {
                            $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total + $ppn - $pph) . '</td></tr>
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
                                            <tr class="line_">
                                            <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;">' . $vp->deskripsi . '</span></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($vp->harga) . '</td></tr>
                                        ');
                                    }
                                }
                            }
                        }
                        ;

                    }

                } else {
                    // cek apakah ada biaya diluar pajak atau tidak
                    if ($pajak == 0) {

                        // cek ada diskon atau tidak
                        if ($diskon != 0 && $diskon != null) {

                            $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total - $diskon + $ppn) . '</td></tr>
                            ');
                        } else {

                            $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH PAJAK</b></span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($sub_total + $ppn) . '</td></tr>
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
                                            <tr class="line_">
                                            <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;">' . $vp->deskripsi . '</span></td>
                                            <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($vp->harga) . '</td></tr>
                                        ');
                                    }
                                }
                            }

                        }
                        ;

                    }

                }

            }


            $pdf->writeHTML('
                <tr class="line_">
                <td style="border: 1px solid; font-size: 10px; padding:3px;" colspan="2"><span><b>TOTAL</b></span></td>
                <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($total_harga) . '</td></tr>
            ');

            $ketDetail = json_decode(json_encode($harga1[0]));

            if ($ketDetail->keterangan == null) {
                $ket = '-';
            } else {
                $ket = $ketDetail->keterangan;
            }

            $pdf->writeHTML('
                <tr><td colspan="5" style="height: 10px;"></td></tr>
                <tr class="line_">
                <td style="border: 1px solid; font-size: 10px; padding: 3px;" colspan="2"><b style="text-transform: uppercase;">' . $ket . '</b></td>
                <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($nilai_tagihan) . '</td></tr>
            ');

            $sisaTagihan = $total_tagihan - $nilai_tagihan;
            if ($sisaTagihan != 0) {
                $pdf->writeHTML('
                    <tr class="line_">
                    <td style="border: 1px solid; font-size: 10px; padding:3px;" colspan="2"><b style="text-transform: uppercase;">SISA PEMBAYARAN</b></td>
                    <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($sisaTagihan) . '</td></tr>
                ');
            }




            $pdf->writeHTML('
                    </tbody>
                </table>
            ');

            $pdf->writeHTML('
                <table style="margin-top: 30px;" width="100%">
                    <tr>
                        <td style="padding-right:50px;">
                        </td>
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
                $filePath = public_path('invoice/' . $fileName);
                $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                // chmod($filePath, 0777);
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
            $konsultant = $customInvoice->data[0]->konsultan || '';
            $jab_pic = '';

            $fileName = 'INVOICE'. '_' . preg_replace('/\\//', '_', $dataHead->no_invoice) . '.pdf';
            $jab_pic = $customInvoice->data[0]->jabatan_pic;

            $qr_img = '';
            if($dataHead->is_generate == 1){
                $qr_name = \str_replace("/", "_", $dataHead->no_invoice);
                $qr = DB::table('qr_documents')->where('file' , $qr_name )->where('type_document', 'invoice')->first();
                if ($qr) $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr . '';
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
                <td colspan="3"><h6 style="font-size:10px; font-weight: bold; white-space: pre-wrap; white-space: -moz-pre-wrap; white-space: -pre-wrap; white-space: -o-pre-wrap; word-wrap: break-word;">' . $konsultant . $customInvoice->data[0]->nama_perusahaan . '</h6></td>
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
                        $dataStructured = self::breakInvoiceDetails($customInvoice->data);
                        // dd($dataStructured);
                        $lastNoQT = ''; // Variabel untuk menyimpan nomor QT terakhir yang dicetak
                        $currentIndex = 1;
                        // dd($dataStructured);
                        foreach ($dataStructured as $k => $invoice) {

                            $printNoQT = $invoice->no_document != $lastNoQT;

                            if ($printNoQT) {
                                $lastNoQT = $invoice->no_document;
                            }
                            // Debugging the invoice details
                            $pdf->writeHTML(
                                '<tr style="border: 1px solid; font-size: 9px;">
                                <td style="font-size:9px;border:1px solid;border-color:#000;text-align:center;" rowspan="' . (count($invoice->invoiceDetails) + 1) . '">' . ($printNoQT ? $currentIndex++ : '') . '</td>
                                    <td style="font-size:9px;border:1px solid;border-color:#000; padding:5px;" rowspan="' . (count($invoice->invoiceDetails) + 1) . '">
                                    <span><b>' . ($printNoQT ? $invoice->no_order : '') . '</b></span><br>
                                    <span><b>' . ($printNoQT ? $invoice->no_document : '') . '</b></span><br>
                                    <span><b>' . ($printNoQT ? ($dataHead->periode && $dataHead->periode != null) ? self::tanggal_indonesia($dataHead->periode, 'period') : '' : '') . '</b></span>
                                    </td>
                                    </tr>'
                                );

                                // Loop untuk menambahkan invoiceDetails
                                foreach ($invoice->invoiceDetails as $k => $itemInvoice) {
                                // Handle empty values
                                $titk = !empty($itemInvoice->titk) ? $itemInvoice->titk : '';
                                $keterangan = !empty($itemInvoice->keterangan) ? $itemInvoice->keterangan : 'No Description';
                                $hargaSatuan = !empty($itemInvoice->harga_satuan) ? self::rupiah($itemInvoice->harga_satuan) : '';
                                $totalHarga = !empty($itemInvoice->total_harga) ? self::rupiah($itemInvoice->total_harga) : '0';

                                // Menulis HTML untuk setiap item invoice
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


                        $pdf->writeHTML('
                        <tr><td style="height: 7px;" colspan="5"></td></tr>
                        <tr class="line_">
                        <td colspan="5"><span style="line-height:normal; font-size:9px;">Terbilang: </span><div><span><b style="font-size:9px; text-align:center; white-space:normal; line-height:normal;">' . self::terbilang($customInvoice->harga->nilai_tagihan != null ? $customInvoice->harga->nilai_tagihan : $customInvoice->harga->total_harga ) . ' Rupiah</b></div></td>
                        <td style="border: 1px solid; font-size: 10px; padding: 3px;" colspan="2"><b>SUB TOTAL</b></td>
                        <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->total_custom) . '</td></tr>
                        ');
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

                        $pdf->writeHTML('
                        <tr>
                                <td rowspan="10" colspan="5">
                                <span style="font-size:10px; border-bottom:1px solid #000; width: 120px; padding-bottom:5px;">Keterangan Pembayaran:</span><br><br>
                                    <span style="font-size:10px;">- Pembayaran dilakukan secara <b style="font-style: italic;">"Full Amount"</b> (tanpa pemotongan biaya apapun)</span><br>
                                    <span style="font-size:10px;">- <b>Cash / Transfer : ' . $dataHead->rekening . ' atas nama PT Inti Surya Laboratorium Bank Central Asia (BCA) - Kota Tangerang - Cabang BSD Serpong</b></span><br>
                                    <span style="font-size:10px">- Pembayaran baru dianggap sah apabila cek / giro telah dapat dicairkan</span><br>
                                    <span style="font-size:10px">- Bukti Pembayaran agar dapat di e-mail ke : billing@intilab.com</span><br>
                                    <span style="font-size:10px">- Invoice asli ini berlaku juga sebagai kwitansi asli yang sah</span><br>
                                    ' . $spk . '
                                    </td>
                                    </tr>
                                    ');

                            if ($customInvoice->harga->total_diskon != 0 && $customInvoice->harga->total_diskon != null) {

                                $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>DISKON</b></span><br><span style="font-size: 7px;">(*Total Diskon)</span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($customInvoice->harga->total_diskon) . '</td></tr>
                                ');

                                $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>TOTAL SETELAH DISKON</b></span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center" class="text-right">' . self::rupiah($customInvoice->harga->total_custom - $customInvoice->harga->total_diskon) . '</td></tr>
                                ');
                            }
                            if ($customInvoice->harga->total_ppn != 0 && $customInvoice->harga->total_ppn != null) {
                                $pdf->writeHTML('
                                <tr class="line_">
                                <td style="border: 1px solid; padding: 3px;" colspan="2"><span style="font-size: 10px;"><b>PPN</b></span><br><span style="font-size: 7px;">(*Total PPN)</span></td>
                                <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->total_ppn) . '</td></tr>
                                ');
                            }
                            if ($customInvoice->harga->total_pph != 0 && $customInvoice->harga->total_pph != null) {
                                $pdf->writeHTML('
                            <tr class="line_">
                            <td style="border: 1px solid; padding:3px;" colspan="2"><span style="font-size: 10px;"><b>PPH</b></span><br><span style="font-size: 7px;">(*Total PPH)</span></td>
                            <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->total_pph) . '</td></tr>
                            ');
                        }
                        $pdf->writeHTML('
                        <tr class="line_">
                        <td style="border: 1px solid; font-size: 10px; padding:3px;" colspan="2"><span><b>TOTAL</b></span></td>
                        <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->total_custom - $customInvoice->harga->total_diskon - $customInvoice->harga->total_pph + $customInvoice->harga->total_ppn) . '</td></tr>
                        ');
                        $pdf->writeHTML('
                        <tr><td colspan="5" style="height: 10px;"></td></tr>
                        <tr class="line_">
                        <td style="border: 1px solid; font-size: 10px; padding: 3px;" colspan="2"><b style="text-transform: uppercase;">' . $dataHead->keterangan . '</b></td>
                        <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->nilai_tagihan) . '</td></tr>
                        ');
                        if ($customInvoice->harga->sisa_tagihan != 0 && $customInvoice->harga->sisa_tagihan != $customInvoice->harga->nilai_tagihan) {
                            $pdf->writeHTML('
                            <tr class="line_">
                            <td style="border: 1px solid; font-size: 10px; padding:3px;" colspan="2"><b style="text-transform: uppercase;">SISA PEMBAYARAN</b></td>
                            <td style="border: 1px solid; font-size: 9px; text-align:center;" class="text-right">' . self::rupiah($customInvoice->harga->sisa_tagihan) . '</td></tr>
                            ');
                        }
                        $pdf->writeHTML('
                        </tbody>
                        </table>
                        ');
                        $pdf->writeHTML('
                        <table style="margin-top: 30px;" width="100%">
                            <tr>
                            <td style="padding-right:50px;">
                            </td>
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


                $filePath = public_path('invoice/' . $fileName);
                $pdf->Output($filePath, \Mpdf\Output\Destination::FILE);
                chmod($filePath, 0777);
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
        if($tanggal == 'all'){
            return 'Semua Periode';
        }

        if($tanggal == 'null'){
            return '';
        }
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

    protected static function breakInvoiceDetails($data) {
        $result = [];

    foreach ($data as $invoice) {
        $invoiceDetails = $invoice->invoiceDetails;
        $chunks = array_chunk($invoiceDetails, 11);

        foreach ($chunks as $chunk) {
            $invoiceCopy = clone $invoice;
            $invoiceCopy->invoiceDetails = $chunk;
            $result[] = $invoiceCopy;
        }
    }

    return $result;
    }
    protected static function breakInvoiceDetailsNonCustom($data) {
        $result = [];

        foreach ($data as $invoice) {
            $details = $invoice['details']; // Akses sebagai array
            $chunks = array_chunk($details, 11);

            foreach ($chunks as $chunk) {
                $invoiceCopy = $invoice; // Salin array, tidak perlu clone
                $invoiceCopy['details'] = $chunk;
                $result[] = $invoiceCopy;
            }
        }
        return $result;
    }
}
