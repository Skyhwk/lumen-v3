<?php

namespace App\Services;

use App\Models\CustomInvoice;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected $pdf;
    protected $data;
    protected $fileName;

    public static function getKeterangan($noInvoice)
    {
        $dataHead = Invoice::with('orderHeaderQuot')->where('is_active', true)
            ->where('no_invoice', $noInvoice)
            ->first();
        if ($dataHead->orderHeaderQuot == null) {
            $quotation = $dataHead->Quotation();
            $status = '';
            if ($quotation) {
                if ($quotation->flag_status == "sp") {
                    $status = 'masih di tahap Sampling Plan';
                } else if ($quotation->flag_status == "draft") {
                    $status = 'masih di tahap Draft';
                } else if ($quotation->flag_status == "emailed") {
                    $status = 'masih di tahap Emailed';
                } else {
                    $status = 'telah di Void';
                }
            } else {
                $status = 'telah di Void';
            }
            return response()->json([
                'message' => 'Quotation ' . $status . '. Silahkan konfirmasi ke divisi Sales.',
            ], 400);
        }
        if ($dataHead->is_custom === 1) {
            $customInvoice = CustomInvoice::where('no_invoice', $noInvoice)->first();

            $dataReturn = json_decode($customInvoice->details, true);
            $hargaReturn = (object)[
                'ppn' => $customInvoice->ppn ?? 0,
                'pph' => $customInvoice->pph ?? 0,
                'total' => $customInvoice->total ?? 0,
                'diskon' => $customInvoice->diskon ?? 0,
                'sub_total' => $customInvoice->sub_total ?? 0,
                'total_pph' => $customInvoice->total_pph ?? 0,
                'total_ppn' => $customInvoice->total_ppn ?? 0,
                'total_harga' => $customInvoice->total_harga ?? 0,
                'sisa_tagihan' => $customInvoice->sisa_tagihan ?? 0,
                'total_custom' => $customInvoice->total_custom ?? 0,
                'total_diskon' => $customInvoice->total_diskon ?? 0,
                'nilai_tagihan' => $customInvoice->nilai_tagihan ?? 0,
                'total_tagihan' => $customInvoice->total_tagihan ?? 0,
            ];
            // dd($hargaReturn);
            return (object)[
                'data' => $dataReturn,
                'harga' => $hargaReturn,
                'dataHead' => $dataHead,
            ];
        } else {
            try {
                $getDetailQt = Invoice::select('no_quotation', 'periode')
                    ->where('is_active', true)
                    ->where('no_invoice', $noInvoice)
                    ->get();

                $dataDetails = [];
                $hargaDetails = [];


                foreach ($getDetailQt as $key => $value) {

                    $noDoc = explode("/", $value->no_quotation);

                    if ($noDoc[1] == 'QTC') {
                        if ($value->periode != "all") {
                            $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot_h.no_document', 'quot_h.wilayah', 'quot_d.data_pendukung_sampling', 'quot_d.transportasi', 'quot_d.harga_transportasi_total', 'quot_d.harga_transportasi', 'quot_d.jumlah_orang_24jam AS jam_jumlah_orang_24', 'quot_d.harga_24jam_personil_total', 'quot_d.perdiem_jumlah_orang', 'quot_d.harga_perdiem_personil_total', 'quot_d.biaya_lain', 'quot_d.grand_total', 'quot_d.discount_air', 'quot_d.total_discount_air', 'quot_d.discount_non_air', 'quot_d.total_discount_non_air', 'quot_d.discount_udara', 'quot_d.total_discount_udara', 'quot_d.discount_emisi', 'quot_d.total_discount_emisi', 'quot_d.discount_transport', 'quot_d.total_discount_transport', 'quot_d.discount_perdiem', 'quot_d.total_discount_perdiem', 'quot_d.discount_perdiem_24jam', 'quot_d.total_discount_perdiem_24jam', 'quot_d.discount_gabungan', 'quot_d.total_discount_gabungan', 'quot_d.discount_consultant', 'quot_d.total_discount_consultant', 'quot_d.discount_group', 'quot_d.total_discount_group', 'quot_d.cash_discount_persen', 'quot_d.total_cash_discount_persen', 'quot_d.cash_discount', 'quot_d.custom_discount', 'quot_h.syarat_ketentuan', 'quot_h.keterangan_tambahan', 'quot_d.total_dpp', 'quot_d.total_ppn', 'quot_d.total_pph', 'quot_d.pph', 'quot_d.total_biaya_di_luar_pajak', 'quot_d.piutang', 'quot_d.biaya_akhir', 'quot_h.is_active', 'quot_h.id_cabang', 'quot_d.biaya_preparasi', 'quot_d.total_biaya_preparasi')
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
                            $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot.*')
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

                        array_push($dataDetails, $dataDetail);
                        array_push($hargaDetails, $hargaDetail);
                    } else {
                        $dataDetail = Invoice::select('invoice.*', 'order_header.*', 'quot.*')
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

                        array_push($dataDetails, $dataDetail);
                        array_push($hargaDetails, $hargaDetail);
                    }
                }

                $isIV = explode('/', $dataHead->no_invoice)[1] == 'IV' ? true : false;
                // dd($dataDetails);
                if ($isIV) {
                    $resultIV = [];
                    $no = 1;

                    foreach ($dataDetails as $k => $valSampling) {

                        $values = json_decode(json_encode($valSampling));
                        $cekArray = json_decode($values->data_pendukung_sampling);

                        // --- periode handling persis punyamu ---
                        $periode = null;
                        if ($values->periode != null && $values->periode != '' && $values->periode != 'null') {
                            if ($values->periode === 'all') {
                                $periode = 'Semua Periode';
                            } else {
                                $periode = self::tanggal_indonesia($values->periode, 'period');
                            }
                        }

                        $allPeriode = ($periode === "Semua Periode");

                        $totalBiayaQt = 0;

                        // ==========================================================
                        // CASE 1: cekArray kosong
                        // ==========================================================
                        if ($cekArray == []) {

                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                $totalBiayaQt += (float) $values->harga_transportasi_total;
                            }

                            $perdiem_24 = '';
                            $total_perdiem = 0;

                            if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                $total_perdiem += (float) $values->harga_24jam_personil_total;
                            }

                            if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                if (isset($values->keterangan_perdiem)) {
                                    $totalBiayaQt += (float) $values->harga_perdiem_personil_total;
                                } else {
                                    $totalBiayaQt += (float) $values->harga_perdiem_personil_total + (float) $total_perdiem;
                                }
                            }

                            if (isset($values->keterangan_lainnya)) {
                                foreach (json_decode($values->keterangan_lainnya) as $ket) {
                                    $totalBiayaQt += (float) $ket->harga_total;
                                }
                            }

                            if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                                if (isset($values->keterangan_biaya_lain)) {
                                    if (is_array($values->keterangan_biaya_lain)) {
                                        foreach ($values->keterangan_biaya_lain as $biayaLain) {
                                            $totalBiayaQt += (float) $biayaLain->total_biaya;
                                        }
                                    } else {
                                        $totalBiayaQt += (float) $values->biaya_lain;
                                    }
                                } else {
                                    $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                    if (is_array($biayaLainArray)) {
                                        foreach ($biayaLainArray as $biayaLain) {
                                            $totalBiayaQt += (float) ($biayaLain['harga'] ?? 0);
                                        }
                                    } else {
                                        $totalBiayaQt += (float) $values->biaya_lain;
                                    }
                                }
                            }
                        } else {

                            // ==========================================================
                            // CASE 2: cekArray array
                            // ==========================================================
                            if (is_array($cekArray)) {

                                $resetData = reset($cekArray);
                                $usingData = (isset($resetData->data_sampling) && is_array($resetData->data_sampling))
                                    ? $resetData->data_sampling
                                    : $cekArray;

                                $chunks = array_chunk($usingData, 15);

                                for ($i = 0; $i < count($chunks); $i++) {
                                    foreach ($chunks[$i] as $dataSampling) {
                                        $split = explode("/", $values->no_document);

                                        if ($split[1] == 'QTC') {
                                            if (isset($dataSampling->keterangan_pengujian)) {
                                                $totalBiayaQt += (float) $dataSampling->harga_total;
                                            } else {
                                                $totalBiayaQt += $allPeriode
                                                    ? (float) $dataSampling->harga_satuan * ((float) $dataSampling->jumlah_titik) * (count($dataSampling->periode))
                                                    : (float) $dataSampling->harga_satuan * ((float) $dataSampling->jumlah_titik);
                                            }
                                        } else {
                                            $totalBiayaQt += (float) $dataSampling->harga_total;
                                        }
                                    }

                                    $isLastElement = $i == count($chunks) - 1;
                                    if ($isLastElement) {

                                        if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                            $totalBiayaQt += (float) $values->harga_transportasi_total;
                                        }

                                        $total_perdiem = 0;
                                        if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                            $total_perdiem += (float) $values->harga_24jam_personil_total;
                                        }

                                        if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                            if (isset($values->keterangan_perdiem)) {
                                                $totalBiayaQt += (float) $values->harga_perdiem_personil_total;
                                            } else {
                                                $totalBiayaQt += (float) $values->harga_perdiem_personil_total + (float) $total_perdiem;
                                            }
                                        }

                                        if (isset($values->keterangan_lainnya)) {
                                            foreach (json_decode($values->keterangan_lainnya) as $ket) {
                                                $totalBiayaQt += (float) $ket->harga_total;
                                            }
                                        }

                                        if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                                            if (isset($values->keterangan_biaya_lain)) {
                                                $totalBiayaQt += (float) $values->biaya_lain;
                                            } else {
                                                $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                                if (is_array($biayaLainArray)) {
                                                    foreach ($biayaLainArray as $biayaLain) {
                                                        $totalBiayaQt += (float) ($biayaLain['harga'] ?? 0);
                                                    }
                                                } else {
                                                    $totalBiayaQt += (float) $values->biaya_lain;
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {

                                // ==========================================================
                                // CASE 3: cekArray object
                                // ==========================================================
                                foreach (json_decode($values->data_pendukung_sampling) as $dataSampling) {

                                    $chunks = array_chunk($dataSampling->data_sampling, 15);

                                    for ($i = 0; $i < count($chunks); $i++) {
                                        foreach ($chunks[$i] as $datasp) {

                                            if (isset($datasp->keterangan_pengujian)) {
                                                $harga_total = (float) $datasp->harga_total;
                                            } else {
                                                $harga_total = (float) $datasp->harga_satuan * (float) $datasp->jumlah_titik;
                                            }

                                            $totalBiayaQt += $harga_total;
                                        }

                                        $isLastElement = $i == count($chunks) - 1;
                                        if ($isLastElement) {

                                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                                $totalBiayaQt += (float) $values->harga_transportasi_total;
                                            }

                                            $total_perdiem = 0;
                                            if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                                $total_perdiem += (float) $values->harga_24jam_personil_total;
                                            }

                                            if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                                if (isset($values->keterangan_perdiem)) {
                                                    $totalBiayaQt += (float) $values->harga_perdiem_personil_total;
                                                } else {
                                                    $totalBiayaQt += (float) $values->harga_perdiem_personil_total + (float) $total_perdiem;
                                                }
                                            }

                                            if (isset($values->keterangan_lainnya)) {
                                                foreach (json_decode($values->keterangan_lainnya) as $ket) {
                                                    $totalBiayaQt += (float) $ket->harga_total;
                                                }
                                            }

                                            if ($values->biaya_lain != null) {
                                                foreach (json_decode($values->biaya_lain) as $biayaL) {
                                                    $totalBiayaQt += (float) $biayaL->harga;
                                                }
                                            }

                                            if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                                $totalBiayaQt += (float) $values->total_biaya_preparasi;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // --- output row per QT ---
                        $resultIV[] = (object)[
                            'no'         => $no,
                            'no_order'   => $values->no_order,
                            'no_document' => $values->no_document,
                            'periode'    => $periode,
                            'keterangan' => 'REIMBURSEMENT BIAYA TRANSPORTASI',
                            // simpen angka mentah (int) biar gampang dipake FE
                            'total_harga' => (int) round($totalBiayaQt),
                        ];

                        $no++;
                    }
                    $valSampling->invoiceDetails = $resultIV;
                } else {
                    foreach ($dataDetails as $k => $valSampling) {

                        $valSampling->invoiceDetails = [];
                        $collectionDetail = [];
                        // dd($valSampling);
                        $values = json_decode(json_encode($valSampling));
                        $cekArray = json_decode($values->data_pendukung_sampling);

                        // --- periode handling (punya $data1) ---
                        $periode = null;
                        if ($values->periode != null && $values->periode != '' && $values->periode != 'null') {
                            if ($values->periode === 'all') {
                                $periode = 'Semua Periode';
                            } else {
                                $periode = self::tanggal_indonesia($values->periode, 'period');
                            }
                        }

                        $allPeriode = ($periode === 'Semua Periode');

                        // ==========================================================
                        // 1) cekArray kosong
                        // ==========================================================
                        if ($cekArray == []) {

                            $tambah = 0;

                            // transportasi
                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                $tambah++;

                                $ket_transportasi = isset($values->keterangan_transportasi)
                                    ? $values->keterangan_transportasi
                                    : "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];

                                $invoiceDetails = (object)[
                                    'keterangan'   => $ket_transportasi,
                                    'titk'         => $values->transportasi,
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi)),
                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }

                            // perdiem
                            $perdiem_24 = '';
                            $total_perdiem = 0;
                            if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                $total_perdiem += $values->harga_24jam_personil_total;
                            }

                            if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                                $tambah++;

                                if (isset($values->keterangan_perdiem)) {
                                    $ket_perdiem = $values->keterangan_perdiem;
                                    $total_perdiem_line = $values->harga_perdiem_personil_total;
                                } else {
                                    $ket_perdiem = "Perdiem " . $perdiem_24;
                                    $total_perdiem_line = $values->harga_perdiem_personil_total + $total_perdiem;
                                }

                                $invoiceDetails = (object)[
                                    'keterangan'   => $ket_perdiem,
                                    'titk'         => 0,
                                    'harga_satuan' => 0,
                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', $total_perdiem_line)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }

                            // keterangan lainnya
                            if (isset($values->keterangan_lainnya)) {
                                $lainnya = json_decode($values->keterangan_lainnya);
                                $tambah += is_array($lainnya) ? count($lainnya) : 0;

                                if (is_array($lainnya)) {
                                    foreach ($lainnya as $ket) {
                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket->deskripsi,
                                            'titk'         => $ket->titik,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }
                            }

                            // biaya lain (punya $data1 logic)
                            if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                                if (isset($values->keterangan_biaya_lain)) {

                                    if (is_array($values->keterangan_biaya_lain)) {
                                        $tambah += count($values->keterangan_biaya_lain);

                                        foreach ($values->keterangan_biaya_lain as $biayaLain) {
                                            $invoiceDetails = (object)[
                                                'keterangan'   => $biayaLain->deskripsi,
                                                'titk'         => null,
                                                'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $biayaLain->harga)),
                                                'total_harga'  => intval(preg_replace('/[^0-9]/', '', $biayaLain->total_biaya)),
                                            ];
                                            array_push($collectionDetail, $invoiceDetails);
                                        }
                                    } else {
                                        // string / json / single
                                        $decoded = json_decode($values->keterangan_biaya_lain);
                                        if (is_array($decoded)) $tambah += count($decoded);
                                        else $tambah += 1;

                                        $invoiceDetails = (object)[
                                            'keterangan'   => (string) $values->keterangan_biaya_lain,
                                            'titk'         => 0,
                                            'harga_satuan' => 0,
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                } else {
                                    $biayaLainArray = json_decode($values->keterangan_biaya, true);

                                    if (is_array($biayaLainArray)) {
                                        $tambah += count($biayaLainArray);

                                        foreach ($biayaLainArray as $biayaLain) {
                                            // di PDF lu cuma nampilin deskripsi + harga
                                            $invoiceDetails = (object)[
                                                'keterangan'   => 'Biaya : ' . ($biayaLain['deskripsi'] ?? 'Biaya Lain'),
                                                'titk'         => 0,
                                                'harga_satuan' => 0,
                                                'total_harga'  => intval(preg_replace('/[^0-9]/', '', ($biayaLain['harga'] ?? 0))),
                                            ];
                                            array_push($collectionDetail, $invoiceDetails);
                                        }
                                    } else {
                                        $tambah += 1;

                                        $invoiceDetails = (object)[
                                            'keterangan'   => 'Biaya Lain-Lain',
                                            'titk'         => 0,
                                            'harga_satuan' => 0,
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }
                            }

                            $rowspan = $tambah + 1;
                            $valSampling['rowspan'] = $rowspan;
                            $valSampling->invoiceDetails = $collectionDetail;
                            continue;
                        }

                        // ==========================================================
                        // 2) cekArray array
                        // ==========================================================
                        if (is_array($cekArray)) {

                            $tambah = 0;

                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) $tambah++;
                            if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) $tambah++;

                            if ($values->biaya_lain) {
                                $decoded = json_decode($values->biaya_lain);
                                if (is_array($decoded)) $tambah += count($decoded);
                                else $tambah += 1;
                            }

                            if (isset($values->keterangan_lainnya)) {
                                $lainnya = json_decode($values->keterangan_lainnya);
                                $tambah += is_array($lainnya) ? count($lainnya) : 0;
                            }

                            // === PUNYA $data1: pakai resetData->data_sampling kalau ada ===
                            $resetData = reset($cekArray);
                            $usingData = (isset($resetData->data_sampling) && is_array($resetData->data_sampling))
                                ? $resetData->data_sampling
                                : $cekArray;

                            $chunks = self::chunkByContentHeight($usingData, $tambah);

                            // isi detail dari sampling
                            for ($i = 0; $i < count($chunks); $i++) {
                                foreach ($chunks[$i] as $keys => $dataSampling) {

                                    $kategori2 = explode("-", $dataSampling->kategori_2);
                                    $split = explode("/", $values->no_document);

                                    if ($split[1] == 'QTC') {
                                        if (isset($dataSampling->keterangan_pengujian)) {
                                            $ket = $dataSampling->keterangan_pengujian . ' Parameter';
                                            $totalHarga = $dataSampling->harga_total;
                                            $titik = ($allPeriode ? $dataSampling->jumlah_titik * count($dataSampling->periode) : $dataSampling->jumlah_titik);
                                        } else {
                                            $ket = strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter . ' Parameter';
                                            // gabung regulasi kayak PDF lu
                                            if (is_array($dataSampling->regulasi)) {
                                                foreach ($dataSampling->regulasi as $v) {
                                                    if ($v != '') {
                                                        $regulasi = explode("-", $v);
                                                        $ket .= ($regulasi[1] ?? '');
                                                    }
                                                }
                                            }
                                            $titik = ($allPeriode ? $dataSampling->jumlah_titik * count($dataSampling->periode) : $dataSampling->jumlah_titik);
                                            $totalHarga = ($allPeriode
                                                ? $dataSampling->harga_satuan * ($dataSampling->jumlah_titik) * (count($dataSampling->periode))
                                                : $dataSampling->harga_satuan * ($dataSampling->jumlah_titik)
                                            );
                                        }

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket,
                                            'titk'         => $titik,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_satuan)),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $totalHarga)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    } else {
                                        // QT non QTC
                                        if (isset($dataSampling->keterangan_pengujian)) {
                                            $ket = $dataSampling->keterangan_pengujian;
                                        } else {
                                            $ket = strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter . ' Parameter';
                                            if (is_array($dataSampling->regulasi)) {
                                                foreach ($dataSampling->regulasi as $v) {
                                                    if ($v != '') {
                                                        $regulasi = explode("-", $v);
                                                        $ket .= ($regulasi[1] ?? '');
                                                    }
                                                }
                                            }
                                        }

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket,
                                            'titk'         => $dataSampling->jumlah_titik,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_satuan)),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_total)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }

                                // tambahan (transportasi, perdiem, lainnya, biaya lain) cuma pas chunk terakhir
                                $isLastElement = $i == count($chunks) - 1;
                                if ($isLastElement) {

                                    // transportasi
                                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                        $ket_transportasi = isset($values->keterangan_transportasi)
                                            ? $values->keterangan_transportasi
                                            : "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket_transportasi,
                                            'titk'         => $values->transportasi,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', ($values->harga_transportasi_total / $values->transportasi))),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }

                                    // perdiem
                                    $perdiem_24 = '';
                                    $total_perdiem = 0;
                                    if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                        $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                        $total_perdiem += $values->harga_24jam_personil_total;
                                    }

                                    if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                        if (isset($values->keterangan_perdiem)) {
                                            $ket_perdiem = $values->keterangan_perdiem;
                                            $total_perdiem_line = $values->harga_perdiem_personil_total;
                                            $jml_perdiem = $values->perdiem_jumlah_orang;

                                            $satuan_perdiem = isset($values->satuan_perdiem)
                                                ? $values->satuan_perdiem
                                                : ($jml_perdiem ? ($total_perdiem_line / $jml_perdiem) : 0);
                                        } else {
                                            $ket_perdiem = "Perdiem " . $perdiem_24;
                                            $total_perdiem_line = $values->harga_perdiem_personil_total + $total_perdiem;
                                            $jml_perdiem = '';
                                            $satuan_perdiem = 0;
                                        }

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket_perdiem,
                                            'titk'         => $jml_perdiem,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $satuan_perdiem)),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $total_perdiem_line)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }

                                    // lainnya
                                    if (isset($values->keterangan_lainnya)) {
                                        $lainnya = json_decode($values->keterangan_lainnya);
                                        if (is_array($lainnya)) {
                                            foreach ($lainnya as $ket) {
                                                $invoiceDetails = (object)[
                                                    'keterangan'   => $ket->deskripsi,
                                                    'titk'         => $ket->titik,
                                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                                                ];
                                                array_push($collectionDetail, $invoiceDetails);
                                            }
                                        }
                                    }

                                    // biaya lain
                                    if ($values->biaya_lain != null) {
                                        $biayaLainArray = json_decode($values->biaya_lain, true);
                                        if (is_array($biayaLainArray)) {
                                            foreach ($biayaLainArray as $biayaLain) {
                                                $invoiceDetails = (object)[
                                                    'keterangan'   => $biayaLain['deskripsi'] ?? 'Biaya Lain',
                                                    'titk'         => $biayaLain['qty'] ?? '',
                                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', ($biayaLain['harga_satuan'] ?? 0))),
                                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', ($biayaLain['harga'] ?? 0))),
                                                ];
                                                array_push($collectionDetail, $invoiceDetails);
                                            }
                                        } else {
                                            $invoiceDetails = (object)[
                                                'keterangan'   => 'Biaya Lain-Lain',
                                                'titk'         => null,
                                                'harga_satuan' => null,
                                                'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                            ];
                                            array_push($collectionDetail, $invoiceDetails);
                                        }
                                    }
                                }
                            }

                            // rowspan: ambil yang terakhir dihitung sesuai pola PDF (chunk last)
                            // kalau lu perlu exact rowspan per chunk, bisa lu simpen juga; tapi minimal ini mirip $dataDetails.
                            $rowspan = (count($chunks) ? (count(end($chunks)) + 1 + $tambah) : ($tambah + 1));
                            $valSampling['rowspan'] = $rowspan;
                            $valSampling->invoiceDetails = $collectionDetail;
                            continue;
                        }

                        // ==========================================================
                        // 3) cekArray object (punya $data1: data_pendukung_sampling object)
                        // ==========================================================
                        foreach (json_decode($values->data_pendukung_sampling) as $keys => $dataSampling) {

                            $tambah = 0;

                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) $tambah++;
                            if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) $tambah++;

                            if ($values->biaya_lain != null) {
                                $decoded = json_decode($values->biaya_lain);
                                $tambah += is_array($decoded) ? count($decoded) : 1;
                            }

                            if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") $tambah++;
                            if (isset($values->keterangan_lainnya)) {
                                $lainnya = json_decode($values->keterangan_lainnya);
                                $tambah += is_array($lainnya) ? count($lainnya) : 0;
                            }

                            // extra_row untuk chunkByContentHeight sesuai $data1
                            $extra_row = 0;
                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) $extra_row++;
                            if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) $extra_row++;

                            if (isset($values->keterangan_lainnya)) {
                                $lainnya = json_decode($values->keterangan_lainnya);
                                if (is_array($lainnya)) foreach ($lainnya as $_) $extra_row++;
                            }

                            if ($values->biaya_lain != null) {
                                $biayaArr = json_decode($values->biaya_lain);
                                if (is_array($biayaArr)) foreach ($biayaArr as $_) $extra_row++;
                                else $extra_row++;
                            }

                            if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") $extra_row++;

                            $chunks = self::chunkByContentHeight($dataSampling->data_sampling, $extra_row);

                            for ($i = 0; $i < count($chunks); $i++) {
                                foreach ($chunks[$i] as $key => $datasp) {

                                    $kategori2 = explode("-", $datasp->kategori_2);

                                    if (isset($datasp->keterangan_pengujian)) {
                                        $ket = $datasp->keterangan_pengujian;
                                        $totalHarga = $datasp->harga_total;
                                    } else {
                                        $ket = strtoupper($kategori2[1]) . ' - ' . $datasp->total_parameter . ' Parameter';
                                        // regulasi bisa string / array
                                        if (is_string($datasp->regulasi)) {
                                            $decodedReg = json_decode($datasp->regulasi, true);
                                            $datasp->regulasi = $decodedReg ?: [];
                                        }
                                        if (!is_array($datasp->regulasi)) $datasp->regulasi = [];

                                        foreach ($datasp->regulasi as $v) {
                                            if ($v != '') {
                                                $regulasi = explode("-", $v);
                                                $ket .= ($regulasi[1] ?? '');
                                            }
                                        }

                                        $totalHarga = $datasp->harga_satuan * $datasp->jumlah_titik;
                                    }

                                    $invoiceDetails = (object)[
                                        'keterangan'   => $ket,
                                        'titk'         => $datasp->jumlah_titik,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $datasp->harga_satuan)),
                                        'total_harga'  => intval(preg_replace('/[^0-9]/', '', $totalHarga)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }

                                $isLastElement = $i == count($chunks) - 1;
                                if ($isLastElement) {
                                    // transportasi
                                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                        $ket_transportasi = isset($values->keterangan_transportasi)
                                            ? $values->keterangan_transportasi
                                            : "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket_transportasi,
                                            'titk'         => $values->transportasi,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', ($values->harga_transportasi_total / $values->transportasi))),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }

                                    // perdiem
                                    $perdiem_24 = '';
                                    $total_perdiem = 0;
                                    if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                                        $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                                        $total_perdiem += $values->harga_24jam_personil_total;
                                    }

                                    if ($values->perdiem_jumlah_orang > 0 && $values->harga_perdiem_personil_total != null) {
                                        if (isset($values->keterangan_perdiem)) {
                                            $ket_perdiem = $values->keterangan_perdiem;
                                            $total_perdiem_line = $values->harga_perdiem_personil_total;
                                            $jml_perdiem = $values->perdiem_jumlah_orang;

                                            $satuan_perdiem = isset($values->satuan_perdiem)
                                                ? $values->satuan_perdiem
                                                : ($jml_perdiem ? ($total_perdiem_line / $jml_perdiem) : 0);
                                        } else {
                                            $ket_perdiem = "Perdiem " . $perdiem_24;
                                            $total_perdiem_line = $values->harga_perdiem_personil_total + $total_perdiem;
                                            $jml_perdiem = '';
                                            $satuan_perdiem = 0;
                                        }

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $ket_perdiem,
                                            'titk'         => $jml_perdiem,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $satuan_perdiem)),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $total_perdiem_line)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }

                                    // lainnya
                                    if (isset($values->keterangan_lainnya)) {
                                        $lainnya = json_decode($values->keterangan_lainnya);
                                        if (is_array($lainnya)) {
                                            foreach ($lainnya as $ket) {
                                                $invoiceDetails = (object)[
                                                    'keterangan'   => $ket->deskripsi,
                                                    'titk'         => $ket->titik,
                                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                                                ];
                                                array_push($collectionDetail, $invoiceDetails);
                                            }
                                        }
                                    }

                                    // biaya lain
                                    if ($values->biaya_lain != null) {
                                        $biayaArr = json_decode($values->biaya_lain);
                                        if (is_array($biayaArr)) {
                                            foreach ($biayaArr as $biayaL) {
                                                $invoiceDetails = (object)[
                                                    'keterangan'   => $biayaL->deskripsi ?? 'Biaya Lain',
                                                    'titk'         => $biayaL->qty ?? '',
                                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', ($biayaL->harga_satuan ?? 0))),
                                                    'total_harga'  => intval(preg_replace('/[^0-9]/', '', ($biayaL->harga ?? 0))),
                                                ];
                                                array_push($collectionDetail, $invoiceDetails);
                                            }
                                        } else {
                                            $invoiceDetails = (object)[
                                                'keterangan'   => 'Biaya Lain-Lain',
                                                'titk'         => null,
                                                'harga_satuan' => null,
                                                'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                            ];
                                            array_push($collectionDetail, $invoiceDetails);
                                        }
                                    }

                                    // preparasi
                                    if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                        $a = json_decode($values->biaya_preparasi);
                                        $collection = collect($a);

                                        $invoiceDetails = (object)[
                                            'keterangan'   => $collection->first()->Deskripsi ?? 'Preparasi',
                                            'titk'         => null,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', ($collection->first()->Harga ?? 0))),
                                            'total_harga'  => intval(preg_replace('/[^0-9]/', '', $values->total_biaya_preparasi)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }
                            }

                            $rowspan = (count($chunks) ? (count(end($chunks)) + 1 + $tambah) : ($tambah + 1));
                            $valSampling['rowspan'] = $rowspan;
                        }

                        $valSampling->invoiceDetails = $collectionDetail;
                    }
                }
                $dataReturn = array_map(function ($item) {
                    // dd($item['invoiceDetails']);
                    return (object)[
                        'konsultan' => $item['konsultan'],
                        'no_order' => $item['no_order'],
                        'no_document' => $item['no_document'],
                        'id_cabang' => $item['id_cabang'],
                        'jabatan_pic' => $item['jabatan_pic'],
                        'no_tlp_perusahaan' => $item['no_tlp_perusahaan'],
                        'nama_perusahaan' => $item['nama_perusahaan'],
                        'invoiceDetails' => $item['invoiceDetails'],
                    ];
                }, $dataDetails);

                $hargaReturn = array_reduce(json_decode(json_encode($hargaDetails)), function ($carry, $item) {
                    foreach ($item as $key => $value) {
                        if (is_numeric($value)) {
                            $carry[$key] = ($carry[$key] ?? 0) + $value;
                        }
                    }
                    return $carry;
                }, []);

                if(!empty($hargaReturn['total_discount'])) {
                    $hargaReturn['diskon'] = $hargaReturn['total_discount'];
                }
                
                return (object)[
                    'data' => $dataReturn,
                    'harga' => (object) $hargaReturn,
                    'dataHead' => $dataHead,
                ];
            } catch (\Throwable $th) {
                dd($th);
            }
        }
    }

    private static function okResult($data, $harga, $dataHead)
    {
        return (object)[
            'ok' => true,
            'data' => $data,
            'harga' => (object)$harga,
            'dataHead' => $dataHead,
            'message' => null,
            'code' => 200,
        ];
    }

    private static function errorResult($message, $code = 400)
    {
        return (object)[
            'ok' => false,
            'data' => [],
            'harga' => (object)[],
            'dataHead' => null,
            'message' => $message,
            'code' => $code,
        ];
    }

    /**
     * Pastikan angka buat kalkulasi itu numeric, bukan string "Rp ..."
     */
    private static function toNumber($val): float
    {
        if ($val === null) return 0.0;
        if (is_int($val) || is_float($val)) return (float)$val;

        if (is_string($val)) {
            $v = trim($val);
            $v = str_replace(['Rp', 'rp', 'RP', ' '], '', $v);

            // dukung format "1.234.567,89" atau "1,234,567.89"
            if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
                // kalau koma ada dan titik ada, anggap yang terakhir itu desimal
                // contoh ID: 1.234.567,89 -> remove titik, koma jadi titik
                // contoh US: 1,234,567.89 -> remove koma
                $lastComma = strrpos($v, ',');
                $lastDot   = strrpos($v, '.');
                if ($lastComma > $lastDot) {
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } else {
                    $v = str_replace(',', '', $v);
                }
            } else {
                // hanya salah satu
                // kalau pakai koma sebagai desimal -> ubah koma jadi titik
                if (strpos($v, ',') !== false) {
                    $v = str_replace('.', '', $v);
                    $v = str_replace(',', '.', $v);
                } else {
                    // pakai titik desimal / ribuan
                    // amanin ribuan: kalau lebih dari 1 titik, remove semua titik
                    if (substr_count($v, '.') > 1) {
                        $v = str_replace('.', '', $v);
                    }
                }
            }

            // bersihin karakter aneh
            $v = preg_replace('/[^0-9\.\-]/', '', $v);
            return (float)($v === '' ? 0 : $v);
        }

        return 0.0;
    }

    private static function rupiah($angka)
    {
        return "Rp " . number_format($angka, 0, '.', ',');
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
