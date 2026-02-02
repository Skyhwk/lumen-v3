<?php

namespace App\Services;

use App\Models\CustomInvoice;
use App\Models\Parameter;
use App\Models\QuotationNonKontrak;
use App\Models\Invoice;
use App\Models\QuotationKontrakD;
use App\Models\QrDocument;
use App\Models\SamplingPlan;
use App\Models\Jadwal;
use Illuminate\Support\Facades\DB;
use Mpdf;

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
            // $dataDecoded = json_decode($dataHead->custom_invoice);

            $customInvoice = CustomInvoice::where('no_invoice', $noInvoice)->first();
            $dataReturn = json_decode($customInvoice->details, true);
            $hargaReturn = (object)[
                'ppn' => $customInvoice->ppn ?? 0,
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
            return (object)(object)[
                'data' => $dataReturn,
                'harga' => (object) $hargaReturn,
                'dataHead' => $dataHead,
            ];
        } else {

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
            // dd($dataDetails);
            foreach ($dataDetails as $k => $valSampling) {
                $valSampling->invoiceDetails = [];
                $collectionDetail = [];
                $values = json_decode(json_encode($valSampling));
                $cekArray = json_decode($values->data_pendukung_sampling);
                // dd($cekArray);
                if ($cekArray == []) {
                    $tambah = 0;

                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                        $tambah = $tambah + 1;
                        if (isset($values->keterangan_transportasi)) {
                            $ket_transportasi = $values->keterangan_transportasi;
                        } else {
                            $ket_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                        }
                        $invoiceDetails = (object) [
                            'keterangan' => $ket_transportasi,
                            'titk' => $values->transportasi,
                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi)),
                            'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                        ];
                        array_push($collectionDetail, $invoiceDetails);
                    }

                    $perdiem_24 = '';
                    $total_perdiem = 0;
                    if ($values->jam_jumlah_orang_24 > 0 && $values->jam_jumlah_orang_24 != null && $values->harga_24jam_personil_total > 0) {
                        $perdiem_24 = 'Termasuk Perdiem (24 Jam)';
                        $total_perdiem = $total_perdiem + $values->harga_24jam_personil_total;
                    }

                    if ($values->harga_perdiem_personil_total != null) {
                        if ($values->harga_perdiem_personil_total > 0) {
                            $tambah = $tambah + 1;
                        }
                        if ($values->perdiem_jumlah_orang > 0) {
                            if (isset($values->keterangan_perdiem)) {
                                $ket_perdiem = $values->keterangan_perdiem;
                                $haga_perdiem_non = $values->harga_perdiem_personil_total;
                            } else {
                                $ket_perdiem = "Perdiem " . $perdiem_24;
                                $haga_perdiem_non = $values->harga_perdiem_personil_total + $total_perdiem;
                            }
                            $invoiceDetails = (object) [
                                'keterangan' => $ket_perdiem,
                                'titk' => 0,
                                'harga_satuan' => 0,
                                'total_harga' => intval(preg_replace('/[^0-9]/', '', $haga_perdiem_non)),
                            ];
                            array_push($collectionDetail, $invoiceDetails);
                        }
                    }

                    if (isset($values->keterangan_lainnya)) {
                        $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                        foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                            $invoiceDetails = (object) [
                                'keterangan' => $ket->deskripsi,
                                'titk' => $ket->titik,
                                'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                'total_harga' => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                            ];
                            array_push($collectionDetail, $invoiceDetails);
                        }
                    }

                    if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                        if (isset($values->keterangan_biaya_lain)) {
                            if (is_array($values->keterangan_biaya_lain)) {
                                $tambah = $tambah + count($values->keterangan_biaya_lain);
                                foreach ($values->keterangan_biaya_lain as $biayaLain) {
                                    $invoiceDetails = (object) [
                                        'keterangan' => $biayaLain->deskripsi,
                                        'titk' => null,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $biayaLain->harga)),
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $biayaLain->total_biaya)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }
                            } else {
                                if (is_array(json_decode($values->keterangan_biaya_lain))) {
                                    $tambah = $tambah + count(json_decode($values->keterangan_biaya_lain));
                                } else {
                                    $tambah = $tambah + 1;
                                }
                                $invoiceDetails = (object) [
                                    'keterangan' => $values->keterangan_biaya_lain,
                                    'titk' => 0,
                                    'harga_satuan' => 0,
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }
                        } else {
                            $biayaLainArray = json_decode($values->keterangan_biaya, true);
                            if (is_array($biayaLainArray)) {
                                $tambah = $tambah + count(json_decode($values->keterangan_biaya));
                                foreach ($biayaLainArray as $biayaLain) {
                                    $invoiceDetails = (object) [
                                        'keterangan' => $biayaLain['deskripsi'],
                                        'titk' => 0,
                                        'harga_satuan' => 0,
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $biayaLain)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }
                            } else {
                                $tambah = $tambah + 1;
                                $invoiceDetails = (object) [
                                    'keterangan' => 'Biaya Lain-Lain',
                                    'titk' => 0,
                                    'harga_satuan' => 0,
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }
                        }
                    }
                    $rowspan = $tambah + 1;
                    $valSampling['rowspan'] = $rowspan;
                } else if (is_array($cekArray)) {
                    $tambah = 0;
                    if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                        $tambah = $tambah + 1;
                    }

                    if ($values->harga_perdiem_personil_total > 0 && $values->harga_perdiem_personil_total != null) {
                        $tambah = $tambah + 1;
                    }

                    if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                        if (is_array(json_decode($values->keterangan_biaya))) {
                            $tambah = $tambah + count(json_decode($values->keterangan_biaya));
                        } else {
                            $tambah = $tambah + 1;
                        }
                    }

                    if (isset($values->keterangan_lainnya)) {
                        $tambah = $tambah + count(json_decode($values->keterangan_lainnya));
                    }
                    for ($i = 0; $i < count(array_chunk($cekArray, 30)); $i++) {
                        foreach (array_chunk($cekArray, 30)[$i] as $keys => $dataSampling) {
                            if ($keys == 0) {
                                if ($i == count(array_chunk($cekArray, 30)) - 1) {
                                    $rowspan = count(array_chunk($cekArray, 30)[$i]) + 1 + $tambah;
                                } else {
                                    $rowspan = count(array_chunk($cekArray, 30)[$i]) + 1;
                                }
                            }
                            $kategori2 = explode("-", $dataSampling->kategori_2);
                            $split = explode("/", $values->no_document);
                            if ($split[1] == 'QTC') {
                                if (isset($dataSampling->keterangan_pengujian)) {
                                    $total_harga_qtc = self::rupiah($dataSampling->harga_total);
                                    $ket_qtc = $dataSampling->keterangan_pengujian . ' Parameter';
                                } else {
                                    $total_harga_qtc = self::rupiah($dataSampling->harga_satuan * ($dataSampling->jumlah_titik * count($dataSampling->periode)));
                                    $ket_qtc = strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter . " Parameter";
                                    foreach ($dataSampling->regulasi as $rg => $v) {
                                        $reg = '';
                                        if ($v != '') {
                                            $regulasi = explode("-", $v);
                                            $reg = $regulasi[1];
                                            $ket_qtc = $ket_qtc . $reg;
                                        }
                                    }
                                }
                                $invoiceDetails = (object) [
                                    'keterangan' => $ket_qtc,
                                    'titk' => $dataSampling->jumlah_titik * count($dataSampling->periode),
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_satuan)),
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $total_harga_qtc)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            } else {
                                if (isset($dataSampling->keterangan_pengujian)) {
                                    $ket_qt = $dataSampling->keterangan_pengujian;
                                } else {
                                    $ket_qt = strtoupper($kategori2[1]) . ' - ' . $dataSampling->total_parameter;
                                    if (is_array($dataSampling->regulasi)) {
                                        foreach ($dataSampling->regulasi as $rg => $v) {
                                            $reg = '';

                                            if ($v != '') {
                                                $regulasi = explode("-", $v);
                                                $reg = $regulasi[1];
                                            }
                                            $ket_qt = $ket_qt . $reg;
                                        }
                                    }
                                }
                                $invoiceDetails = (object) [
                                    'keterangan' => $ket_qt,
                                    'titk' => $dataSampling->jumlah_titik,
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_satuan)),
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $dataSampling->harga_total)),
                                ];
                            }
                            // dd('masuk');

                            array_push($collectionDetail, $invoiceDetails);
                        }
                        $isLastElement = $i == count(array_chunk($cekArray, 30)) - 1;
                        if ($isLastElement) {
                            if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {
                                if (isset($values->keterangan_transportasi)) {
                                    $ket_transportasi = $values->keterangan_transportasi;
                                } else {
                                    $ket_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                }
                                $invoiceDetails = (object) [
                                    'keterangan' => $ket_transportasi,
                                    'titk' => $values->transportasi,
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi)),
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
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
                                    $jml_perdiem = $values->perdiem_jumlah_orang;
                                    if (isset($values->satuan_perdiem)) {
                                        $satuan_perdiem = self::rupiah($values->satuan_perdiem);
                                    } else {
                                        $sdiem = $harga_perdiem / $jml_perdiem;
                                        $satuan_perdiem = self::rupiah($sdiem);
                                    }
                                } else {
                                    $ket_perdiem = "Perdiem " . $perdiem_24;
                                    $haga_perdiem_non = $values->harga_perdiem_personil_total + $total_perdiem;
                                    $jml_perdiem = '';
                                    $satuan_perdiem = '';
                                }

                                $invoiceDetails = (object) [
                                    'keterangan' => $ket_perdiem,
                                    'titk' => $jml_perdiem,
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $satuan_perdiem)),
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $haga_perdiem_non)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }
                            if (isset($values->keterangan_lainnya)) {
                                foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                    $invoiceDetails = (object) [
                                        'keterangan' => $ket->deskripsi,
                                        'titk' => $ket->titik,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }
                            }
                            if ($values->biaya_lain != null && $values->biaya_lain > 0) {
                                if (isset($values->keterangan_biaya_lain)) {
                                    $invoiceDetails = (object) [
                                        'keterangan' => $values->keterangan_biaya_lain,
                                        'titk' => null,
                                        'harga_satuan' => null,
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                } else {
                                    $biayaLainArray = json_decode($values->keterangan_biaya, true);
                                    if (is_array($biayaLainArray)) {
                                        foreach ($biayaLainArray as $biayaLain) {
                                            $invoiceDetails = (object) [
                                                'keterangan' => $biayaLain['deskripsi'],
                                                'titk' => null,
                                                'harga_satuan' => null,
                                                'total_harga' => intval(preg_replace('/[^0-9]/', '', $biayaLain['harga'])),
                                            ];
                                            array_push($collectionDetail, $invoiceDetails);
                                        }
                                    } else {
                                        $invoiceDetails = (object) [
                                            'keterangan' => 'Biaya Lain-Lain',
                                            'titk' => null,
                                            'harga_satuan' => null,
                                            'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->biaya_lain)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }
                            }
                        }
                    }
                    $valSampling['rowspan'] = $rowspan;
                } else {
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
                        for ($i = 0; $i < count(array_chunk($dataSampling->data_sampling, 20)); $i++) {
                            foreach (array_chunk($dataSampling->data_sampling, 20)[$i] as $key => $datasp) {
                                if ($values->periode != null) {
                                    $pr = $values->periode . ' Period';
                                } else {
                                    $pr = "";
                                }
                                if ($key == 0) {
                                    if ($i == count(array_chunk($dataSampling->data_sampling, 20)) - 1) {
                                        $rowspan = count(array_chunk($dataSampling->data_sampling, 20)[$i]) + 1 + $tambah;
                                    } else {
                                        $rowspan = count(array_chunk($dataSampling->data_sampling, 20)[$i]) + 1;
                                    }
                                }
                                $kategori2 = explode("-", $datasp->kategori_2);
                                if (isset($datasp->keterangan_pengujian)) {
                                    $keterangan_pengujian = $datasp->keterangan_pengujian;
                                    $harga_total = self::rupiah($datasp->harga_total);
                                } else {
                                    $keterangan_pengujian = strtoupper($kategori2[1]) . ' - ' . $datasp->total_parameter . ' Parameter';
                                    $harga_total = $datasp->harga_satuan * $datasp->jumlah_titik;

                                    foreach ($datasp->regulasi as $rg => $v) {
                                        $reg = '';
                                        if ($v != '') {
                                            $regulasi = explode("-", $v);
                                            $reg = $regulasi[1];
                                        }
                                        $keterangan_pengujian = $keterangan_pengujian . $reg;
                                    }
                                }
                                $invoiceDetails = (object) [
                                    'keterangan' => $keterangan_pengujian,
                                    'titk' => $datasp->jumlah_titik,
                                    'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $datasp->harga_satuan)),
                                    'total_harga' => intval(preg_replace('/[^0-9]/', '', $harga_total)),
                                ];
                                array_push($collectionDetail, $invoiceDetails);
                            }

                            $isLastElement = $i == count(array_chunk($dataSampling->data_sampling, 20)) - 1;

                            if ($isLastElement) {
                                if ($values->transportasi > 0 && $values->harga_transportasi_total != null) {

                                    if (isset($values->keterangan_transportasi)) {
                                        $keterangan_transportasi = $values->keterangan_transportasi;
                                    } else {

                                        $keterangan_transportasi = "Transportasi - Wilayah Sampling : " . explode("-", $values->wilayah)[1];
                                    }

                                    $invoiceDetails = (object) [
                                        'keterangan' => $keterangan_transportasi,
                                        'titk' => $values->transportasi,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total / $values->transportasi)),
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->harga_transportasi_total)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
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

                                    $invoiceDetails = (object) [
                                        'keterangan' => $keterangan_perdiem,
                                        'titk' => $jml_perdiem,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $satuan_perdiem)),
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $harga_perdiem)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }

                                if (isset($values->keterangan_lainnya)) {
                                    foreach (json_decode($values->keterangan_lainnya) as $k => $ket) {
                                        $invoiceDetails = (object) [
                                            'keterangan' => $ket->deskripsi,
                                            'titk' => $ket->titik,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $ket->harga_satuan)),
                                            'total_harga' => intval(preg_replace('/[^0-9]/', '', $ket->harga_total)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }

                                if ($values->biaya_lain != null) {
                                    foreach (json_decode($values->biaya_lain) as $b => $biayaL) {
                                        $qtyB = isset($biayaL->qty) ? $biayaL->qty : '';
                                        $hargaSatuanB = isset($biayaL->harga_satuan) ? self::rupiah($biayaL->harga_satuan) : '';
                                        $invoiceDetails = (object) [
                                            'keterangan' => $biayaL->deskripsi,
                                            'titk' => $qtyB,
                                            'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $hargaSatuanB)),
                                            'total_harga' => intval(preg_replace('/[^0-9]/', '', $biayaL->harga)),
                                        ];
                                        array_push($collectionDetail, $invoiceDetails);
                                    }
                                }

                                if (isset($values->biaya_preparasi) && $values->biaya_preparasi != "[]") {
                                    $a = json_decode($values->biaya_preparasi);
                                    $collection = collect($a);

                                    $invoiceDetails = (object) [
                                        'keterangan' => $collection->first()->Deskripsi,
                                        'titk' => null,
                                        'harga_satuan' => intval(preg_replace('/[^0-9]/', '', $collection->first()->Harga)),
                                        'total_harga' => intval(preg_replace('/[^0-9]/', '', $values->total_biaya_preparasi)),
                                    ];
                                    array_push($collectionDetail, $invoiceDetails);
                                }
                            }
                        }
                        $valSampling['rowspan'] = $rowspan;
                    }
                }
                $valSampling->invoiceDetails = $collectionDetail;
                // Hapus data duplikat dari $collectionDetail
                // $uniqueCollection = [];
                // $uniqueKeys = [];

                // foreach ($collectionDetail as $detail) {
                //     $key = $detail->keterangan . '|' . $detail->titk . '|' . $detail->harga_satuan . '|' . $detail->total_harga;
                //     if (!in_array($key, $uniqueKeys)) {
                //         $uniqueKeys[] = $key;
                //         $uniqueCollection[] = $detail;
                //     }
                // }

                // $valSampling->invoiceDetails = $uniqueCollection;
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
            return (object)[
                'data' => $dataReturn,
                'harga' => (object) $hargaReturn,
                'dataHead' => $dataHead,
            ];
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
}
