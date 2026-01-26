<?php

namespace App\Http\Controllers\api;

use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\OrderHeader;
use App\Models\MasterKaryawan;
use App\Models\GenerateLink;
use App\Models\Invoice;
use App\Services\GenerateToken;
use App\Http\Controllers\Controller;
use App\Services\RenderInvoice;
use App\Services\SendEmail;
use App\Services\GetAtasan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;


class GenerateInvoiceController extends Controller
{
    private static function generatePDF($noInvoice)
    {
        $render = new RenderInvoice();
        $render->renderInvoice($noInvoice);
        return true;
    }

    public function updateAlamatPenagihan(Request $request)
    {
        // dd($request->all());
        $nomorInvoice = Invoice::where('no_invoice', $request->no_invoice)->first();

        if (!$nomorInvoice) {
            return response()->json([
                'message' => 'No Invoice Tidak Ditemukan'
            ], 400);
        }

        if (str_contains($nomorInvoice->no_quotation, '/QTC')) {
            $quot = QuotationKontrakH::where('no_document', $nomorInvoice->no_quotation)->first();

            if (!$quot) {
                return response()->json([
                    'message' => 'No Quotation Tidak Ditemukan'
                ], 400);
            }

            $detailQuot = QuotationKontrakD::where('id_request_quotation_kontrak_h', $quot->id)
                ->where('periode_kontrak', $nomorInvoice->periode)
                ->first();

            if (!$detailQuot) {
                return response()->json([
                    'message' => 'No Quotation Tidak Ditemukan'
                ], 400);
            }

            $totalDiscounts = $detailQuot->total_discount_air
                + $detailQuot->total_discount_non_air
                + $detailQuot->total_discount_udara
                + $detailQuot->total_discount_gabungan
                + $detailQuot->total_discount_emisi
                + $detailQuot->total_cash_discount_persen
                + $detailQuot->total_discount_group
                + $detailQuot->total_discount_consultant
                + $detailQuot->total_custom_discount
                + $detailQuot->total_discount_transport
                + $detailQuot->total_discount_pardiem
                + $detailQuot->total_discount_pardiem24jam;

            $detailQuot->update(['total_discount' => $totalDiscounts]);

            $this->generatePDF($nomorInvoice->no_invoice);

            return response()->json(['message' => 'berhasil']);
        }

        return response()->json([
            'message' => 'No Invoice Tidak Kontrak'
        ], 400);
    }

    public function getSomeInvoice(Request $request)
    {
        dd($request->all());
        $nomorInvoice = Invoice::where('no_invoice', $request->no_invoice)->first();

        if (!$nomorInvoice) {
            return response()->json([
                'message' => 'No Invoice Tidak Ditemukan'
            ], 400);
        }

        if (str_contains($nomorInvoice->no_quotation, '/QTC')) {
            $quot = QuotationKontrakH::where('no_document', $nomorInvoice->no_quotation)->first();

            if (!$quot) {
                return response()->json([
                    'message' => 'No Quotation Tidak Ditemukan'
                ], 400);
            }

            $detailQuot = QuotationKontrakD::where('id_request_quotation_kontrak_h', $quot->id)
                ->where('periode_kontrak', $nomorInvoice->periode)
                ->first();

            if (!$detailQuot) {
                return response()->json([
                    'message' => 'No Quotation Tidak Ditemukan'
                ], 400);
            }

            $totalDiscounts = $detailQuot->total_discount_air
                + $detailQuot->total_discount_non_air
                + $detailQuot->total_discount_udara
                + $detailQuot->total_discount_gabungan
                + $detailQuot->total_discount_emisi
                + $detailQuot->total_cash_discount_persen
                + $detailQuot->total_discount_group
                + $detailQuot->total_discount_consultant
                + $detailQuot->total_custom_discount
                + $detailQuot->total_discount_transport
                + $detailQuot->total_discount_pardiem
                + $detailQuot->total_discount_pardiem24jam;

            $detailQuot->update(['total_discount' => $totalDiscounts]);

            $this->generatePDF($nomorInvoice->no_invoice);

            return response()->json(['message' => 'berhasil']);
        }

        return response()->json([
            'message' => 'No Invoice Tidak Kontrak'
        ], 400);
    }

    //2025-08-02
    public function index(Request $request)
    {
        try {
            // if (isset($request->tgl_akhir) && $request->tgl_akhir != null) {
            //     $db = $request->tgl_akhir;
            // } else {
            //     return response()->json(['data' => [], 'message' => 'Tanggal Transaksi Tidak Ada.!'], 201);
            // }

            $data = Invoice::select(
                'invoice.no_invoice',
                DB::raw('MAX(invoice.created_by) AS created_by'),
                DB::raw('MAX(faktur_pajak) AS faktur_pajak'),
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
                DB::raw('MAX(rekening) AS rekening'),
                DB::raw('MAX(periode) AS periode_kontrak'), //05/02/2025
                DB::raw('MAX(keterangan) AS keterangan'),
                DB::raw('MAX(nama_pj) AS nama_pj'),
                DB::raw('MAX(jabatan_pj) AS jabatan_pj'),
                DB::raw('MAX(tgl_invoice) AS tgl_invoice'),
                DB::raw('MAX(no_faktur) AS no_faktur'),
                DB::raw('MAX(alamat_penagihan) AS alamat_penagihan'),
                DB::raw('MAX(nama_pic) AS nama_pic'),
                DB::raw('MAX(no_pic) AS no_pic'),
                DB::raw('MAX(email_pic) AS email_pic'),
                DB::raw('MAX(is_custom) AS is_custom'),
                DB::raw('MAX(invoice.keterangan_tambahan) AS keterangan_tambahan'),
                DB::raw('MAX(jabatan_pic) AS jabatan_pic'),
                DB::raw('MAX(invoice.no_po) AS no_po'),
                DB::raw('MAX(no_spk) AS no_spk'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(filename) AS filename'),
                DB::raw('MAX(file_pph) AS file_pph'),
                DB::raw('MAX(upload_file) AS upload_file'),
                DB::raw('MAX(order_header.konsultan) AS consultant'),
                DB::raw('MAX(order_header.no_document) AS document'),
                DB::raw('MAX(invoice.created_at) AS created_at'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(is_generate) AS is_generate'),
                DB::raw('MAX(generated_by) AS generated_by'),
                DB::raw('MAX(generated_at) AS generated_at'),
                DB::raw('MAX(expired) AS expired'),
                DB::raw('MAX(invoice.pelanggan_id) AS pelanggan_id'),
                DB::raw('MAX(invoice.detail_pendukung) AS detail_pendukung'),
                DB::raw('MAX(invoice.nama_perusahaan) AS nama_customer'),
                // DB::raw('COALESCE(MAX(order_header.konsultan), MAX(nama_perusahaan)) AS nama_customer'),
                DB::raw('SUM(invoice.nilai_tagihan) AS nilai_tagihan'),
                DB::raw('MAX(order_header.is_revisi) AS is_revisi'),
                DB::raw('GROUP_CONCAT(invoice.no_order) AS no_orders'),
                DB::raw('GROUP_CONCAT(CONCAT(order_header.no_document, "_", invoice.no_order)) AS document_order')
            )
                ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                ->groupBy('invoice.no_invoice')
                ->where('is_emailed', false)
                ->where('invoice.is_active', true)
                ->where('order_header.is_active', true)
                ->orderBy('invoice.no_invoice', 'DESC')
                ->get();

            return Datatables::of($data)->make(true);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    public function getUser(Request $request)
    {
        $users = MasterKaryawan::with(['department', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

        return response()->json($users);
    }

    public function getEmailCC(Request $request)
    {
        // dd($request->all());
        $emails = ['billing@intilab.com'];
        $cek = explode("/", $request->document)[1];
        if ($cek === 'QTC') {
            $salesIds = QuotationKontrakH::where('no_document', $request->document)->pluck('sales_id');
            // dd($salesIds);
        } else {
            $salesIds = QuotationNonKontrak::where('no_document', $request->document)->pluck('sales_id');
        }
        if ($salesIds->isNotEmpty()) {
            $emailsSales = MasterKaryawan::where('id', $salesIds)->pluck('email')->toArray();
            $atasan = GetAtasan::where('id', $salesIds)->get();
            $emailsAtasan = $atasan->where('grade', 'SUPERVISOR')->pluck('email')->toArray();
            $emails = array_merge($emails, $emailsSales, $emailsAtasan);
        }
        // dd($emails);
        return response()->json($emails);
    }

    public function getLink(Request $request)
    {
        $link = GenerateLink::where(['id_quotation' => $request->id, 'quotation_status' => $request->mode, 'type' => 'quotation'])->latest()->first();
        return response()->json(['link' => env('PORTALV3_LINK') . $link->token], 200);
    }

    public function getDataEmail(Request $request)
    {
        $invoice = Invoice::with('orderHeaderQuot')->where('no_invoice', $request->no_invoice)->where('is_active', true)->first();
        if ($invoice->orderHeaderQuot == null) {
            $quotation = $invoice->Quotation();
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
        try {
            $invoiceDatas = Invoice::select(
                'invoice.no_invoice',
                DB::raw('MIN(order_detail.tanggal_sampling) as tanggal_min'),
                DB::raw('MAX(order_detail.tanggal_sampling) as tanggal_max'),
                DB::raw('MAX(invoice.id_token) as id_token')
            )
                ->join('order_detail', 'order_detail.no_order', '=', 'invoice.no_order')
                ->where('invoice.no_invoice', $request->no_invoice)
                ->where('invoice.is_active', true)
                ->groupBy('invoice.no_invoice')
                ->get();

            $result = $invoiceDatas->map(function ($data) {
                return [
                    'no_invoice' => $data->no_invoice,
                    'tanggal_awal_sampling' => $data->tanggal_min,
                    'tanggal_akhir_sampling' => $data->tanggal_min === $data->tanggal_max ? null : $data->tanggal_max,
                ];
            });

            //Email
            $emails = ['sales@intilab.com'];
            if ($request->email_cc) {
                $emailCC = json_encode($request->email_cc);
                foreach (json_decode($emailCC) as $item)
                    $emails[] = $item;
            }
            $users = GetAtasan::where('id', $request->sales_id ?: $this->user_id)->get()->pluck('email');
            foreach ($users as $item)
                $emails[] = $item == 'novva@intilab.com' ? 'sales02@intilab.com' : $item;

            // user
            $user = MasterKaryawan::with(['divisi', 'jabatan'])->where('id', $request->id ?: $this->user_id)->first();

            //Link
            // dd($invoiceDatas);
            if ($invoiceDatas->isEmpty()) {
                $invoiceDatas = Invoice::where('no_invoice', $request->no_invoice)
                    ->where('is_active', true)
                    ->first();

                $link = GenerateLink::where(['id' => $invoiceDatas->id_token, 'quotation_status' => 'invoice', 'type' => 'invoice'])->latest()->first();
                $invoiceDatas->tanggal_max = '';
                $invoiceDatas->tanggal_min = '';
                return response()->json([
                    'emails' => $emails,
                    'no_invoice' => $request->no_invoice,
                    'data' => $invoiceDatas,
                    'link' => env('PORTALV3_LINK') . $link->token,
                    'user' => $user,
                ], 200);
            }
            // dd($invoiceDatas);
            $link = GenerateLink::where(['id' => $invoiceDatas[0]->id_token, 'quotation_status' => 'invoice', 'type' => 'invoice'])->latest()->first();
            // response
            // dd($invoiceDatas);
            return response()->json([
                'emails' => $emails,
                'no_invoice' => $request->no_invoice,
                'data' => $invoiceDatas[0],
                'link' => env('PORTALV3_LINK') . $link->token,
                'user' => $user,
            ], 200);
        } catch (\Exception $th) {
            dd($th);
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function updateData(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try {
            $expired = date('Y-m-d', strtotime($request['tgl_tempo'] . ' + 2 years'));

            $invoice = Invoice::select('piutang')
                ->where('no_invoice', $request->no_invoice)
                ->where('is_active', true)
                ->get();

            $orders = explode(",", $request->no_orders);

            $simpanHarga = 0;
            foreach ($orders as $key => $value) {
                $getDetail = OrderHeader::select('order_header.biaya_akhir', 'invoice.periode', 'invoice.custom_invoice', 'invoice.is_custom')
                    ->where('order_header.no_order', $value)
                    ->leftJoin('invoice', 'invoice.no_order', '=', 'order_header.no_order')
                    ->where('order_header.is_active', true)
                    ->first();

                $bagiHarga = preg_replace('/[Rp., ]/', '', $request->nilai_tagihan) / count($invoice);
                $cekHarga = $getDetail->biaya_akhir - $bagiHarga;

                if ($cekHarga < 0) {
                    $simpanHarga += abs($cekHarga);
                    $nilaiTagihan = $getDetail->biaya_akhir;
                } else {
                    $nilaiTagihan = $bagiHarga + $simpanHarga;
                }

                $customInvoice = null;
                if ($getDetail->is_custom) {
                    $customInvoice = json_decode($getDetail->custom_invoice, true);

                    if (isset($customInvoice['data']) && is_array($customInvoice['data'])) {
                        foreach ($customInvoice['data'] as &$dataItem) {
                            if (isset($dataItem['no_order']) && $dataItem['no_order'] === $value) {
                                // Update nilai_tagihan
                                if (isset($customInvoice['harga'])) {
                                    $customInvoice['harga']['nilai_tagihan'] = preg_replace('/[Rp., ]/', '', $request->nilai_tagihan);
                                    $customInvoice['harga']['sisa_tagihan'] = $customInvoice['harga']['total_custom'] + $customInvoice['harga']['total_ppn'] - $customInvoice['harga']['total_diskon'] - $customInvoice['harga']['nilai_tagihan'];
                                }
                                break;
                            }
                        }
                    }
                }

                $encodedCustomInvoice = json_encode($customInvoice);

                $update = [
                    'periode' => $request->periode_kontrak,
                    'faktur_pajak' => $request->faktur_pajak,
                    'nama_perusahaan' => $request->nama_perusahaan,
                    'no_faktur' => $request->no_faktur,
                    'no_spk' => $request->no_spk,
                    'no_po' => $request->no_po,
                    'tgl_jatuh_tempo' => $request->tgl_jatuh_tempo,
                    'keterangan_tambahan' => $request->keterangan_tambahan ? json_encode($request->keterangan_tambahan) : null,
                    'tgl_faktur' => Carbon::now(),
                    'tgl_invoice' => $request->tgl_invoice,
                    'nilai_tagihan' => $nilaiTagihan,
                    'total_tagihan' => preg_replace('/[Rp., ]/', '', $request->total_tagihan) / count($invoice),
                    'nama_pj' => $request->nama_pj,
                    'jabatan_pj' => $request->jabatan_pj,
                    'keterangan' => $request->keterangan,
                    'alamat_penagihan' => $request->alamat_penagihan,
                    'nama_pic' => $request->nama_pic,
                    'no_pic' => $request->no_pic,
                    'email_pic' => $request->email_pic,
                    'jabatan_pic' => $request->jabatan_pic,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now(),
                    'is_active' => true,
                    'expired' => $expired,
                    'custom_invoice' => $encodedCustomInvoice,
                ];

                Invoice::where('no_invoice', $request->no_invoice)
                    ->where('is_active', true)
                    ->where('no_order', $value)
                    ->update($update);
            }

            $filename = \str_replace("/", "_", $request->no_invoice);
            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            if(!file_exists($path)){
                $invoice = Invoice::where('no_invoice', $request->no_invoice)->where('is_active', true)->first();
                $link = 'https://www.intilab.com/validation/';
                $unique = 'isldc' . (int) floor(microtime(true) * 1000);

                $getDetail = Invoice::select('order_header.nama_perusahaan')
                    ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                    ->where('invoice.no_invoice', $request->no_invoice)
                    ->where('invoice.is_active', true)
                    ->first();

                QrCode::size(200)->generate($link . $unique, $path);
                $dataQr = [
                    'type_document' => 'invoice',
                    'kode_qr' => $unique,
                    'file' => $filename,
                    'data' => json_encode([
                        'no_document' => $request->no_invoice,
                        'nama_customer' => $getDetail->nama_perusahaan,
                        'type_document' => 'invoice',
                        'Tanggal_Pengesahan' => Carbon::parse($invoice->tgl_invoice)->locale('id')->isoFormat('DD MMMM YYYY'),
                        'Disahkan_Oleh' => $invoice->nama_pj,
                        'Jabatan' => $invoice->jabatan_pj
                    ]),
                    'created_at' => Carbon::now(),
                    'created_by' => $this->karyawan,
                ];

                DB::table('qr_documents')->insert($dataQr);
                // self::generatePDF($request->no_invoice);
            }

            self::generatePDF($request->no_invoice);

            DB::commit();
            return response()->json([
                'message' => 'Data Hasbeen Update',
            ], 201);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        $updatedCount = Invoice::where('no_invoice', $request->no_invoice)
            ->update(
                [
                    'is_active' => false,
                    'keterangan_delete' => $request->reason,
                    'deleted_by' => $this->karyawan,
                    'deleted_at' => Carbon::now(),
                ]
            );

        return response()->json([
            'message' => "$updatedCount data in invoice deleted."
        ]);
    }

    public function rollbackCustom(Request $request)
    {
        $updatedCount = Invoice::where('no_invoice', $request->no_invoice)
            ->update(
                [
                    'is_custom' => false,
                    'custom_invoice' => null,
                ]
            );

        self::generatePDF($request->no_invoice);

        return response()->json([
            'message' => "Custom payroll has been rollback."
        ]);
    }

    public function sendEmail(Request $request)
    {
        try {
            // $email = SendEmail::where('to', 'aliffadhil@intilab.com')
            $email = SendEmail::where('to', $request->to)
                ->where('subject', $request->subject)
                ->where('body', $request->content)
                ->where('cc', $request->cc)
                ->where('bcc', $request->bcc)
                ->where('attachments', $request->attachments)
                ->where('karyawan', $this->karyawan)
                ->noReply()
                ->send();

            if ($email) {
                Invoice::where('no_invoice', $request->no_invoice)
                    ->update([
                        'is_emailed' => true,
                        'emailed_at' => Carbon::now()->format('Y-m-d H:i:s'),
                        'emailed_by' => $this->karyawan,
                    ]);

                return response()->json([
                    'message' => 'Email berhasil dikirim'
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Email gagal dikirim'
                ], 400);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage()
            ], 500);
        }
    }

    // public function generate(Request $request)
    // {
    //     if ($request->no_invoice != '') {
    //         DB::beginTransaction();
    //         try {
    //             if ($request->tgl_order == null) {
    //                 return response()->json(['data' => [], 'message' => 'Tanggal Transaksi Tidak Ada.!'], 201);
    //             }

    //             $invoice = Invoice::where('no_invoice', $request->no_invoice)
    //                 ->where('is_active', true)
    //                 ->first();

    //             if ($invoice->is_generate == 1) {
    //                 return response()->json(['message' => "Invoice sudah tergenarte tanggal $invoice->generated_at"], 201);
    //             } else {
    //                 $filename = \str_replace("/", "_", $request->no_invoice);
    //                 $path = public_path() . "/qr_documents/" . $filename . '.svg';
    //                 $link = 'https://www.intilab.com/validation/';
    //                 $unique = 'isldc' . (int) floor(microtime(true) * 1000);

    //                 $getDetail = Invoice::select('order_header.nama_perusahaan')
    //                     ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
    //                     ->where('invoice.no_invoice', $request->no_invoice)
    //                     ->where('invoice.is_active', true)
    //                     ->first();

    //                 QrCode::size(200)->generate($link . $unique, $path);
    //                 // dd($getDetail);
    //                 $dataQr = [
    //                     'type_document' => 'invoice',
    //                     'kode_qr' => $unique,
    //                     'file' => $filename,
    //                     'data' => json_encode([
    //                         'no_document' => $request->no_invoice,
    //                         'nama_customer' => $getDetail->nama_perusahaan,
    //                         'type_document' => 'invoice'
    //                     ]),
    //                     'created_at' => DATE('Y-m-d H:i:s'),
    //                     'created_by' => $this->karyawan,
    //                 ];

    //                 DB::table('qr_documents')->insert($dataQr);


    //                 $token = GenerateToken::save('INVOICE', $invoice, $this->karyawan, 'invoice');

    //                 Invoice::where('no_invoice', $request->no_invoice)->update([
    //                     'id_token' => $token->id,
    //                     'is_generate' => 1,
    //                     'generated_at' => Carbon::now(),
    //                     'generated_by' => $this->karyawan
    //                 ]);
    //             }

    //             self::generatePDF($request->no_invoice);

    //             $message = "Invoice number $invoice->no_invoice success Generated";
    //             DB::commit();
    //             return response()->json(['message' => $message, 'status' => 200], 200);
    //         } catch (\Throwable $th) {
    //             DB::rollback();
    //             dd($th);
    //             return response()->json(['message' => $th->getMessage()], 401);
    //         }
    //     } else {
    //         DB::rollback();
    //         return response()->json(['message' => 'Data not Found.!'], 401);
    //     }
    // }

    public function generate(Request $request)
    {
        if ($request->no_invoice != '') {
            $invoice = Invoice::with('orderHeaderQuot')->where('no_invoice', $request->no_invoice)->where('is_active', true)->first();
            // dd($invoice);
            if ($invoice->orderHeaderQuot == null) {
                $quotation = $invoice->Quotation();
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
            DB::beginTransaction();
            try {
                if ($request->tgl_order == null) {
                    return response()->json(['data' => [], 'message' => 'Tanggal Transaksi Tidak Ada.!'], 201);
                }

                $invoice = Invoice::where('no_invoice', $request->no_invoice)
                    ->where('is_active', true)
                    ->first();
                if ($invoice->is_generate == 1) {
                    return response()->json(['message' => "Invoice sudah tergenarte tanggal $invoice->generated_at"], 201);
                } else {
                    $filename = \str_replace("/", "_", $request->no_invoice);
                    $path = public_path() . "/qr_documents/" . $filename . '.svg';
                    if(!file_exists($path)){
                        $link = 'https://www.intilab.com/validation/';
                        $unique = 'isldc' . (int) floor(microtime(true) * 1000);
    
                        $getDetail = Invoice::select('order_header.nama_perusahaan')
                            ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
                            ->where('invoice.no_invoice', $request->no_invoice)
                            ->where('invoice.is_active', true)
                            ->first();
    
                        QrCode::size(200)->generate($link . $unique, $path);
                        $dataQr = [
                            'type_document' => 'invoice',
                            'kode_qr' => $unique,
                            'file' => $filename,
                            'data' => json_encode([
                                'no_document' => $request->no_invoice,
                                'nama_customer' => $getDetail->nama_perusahaan,
                                'type_document' => 'invoice',
                                'Tanggal_Pengesahan' => Carbon::parse($invoice->tgl_invoice)->locale('id')->isoFormat('DD MMMM YYYY'),
                                'Disahkan_Oleh' => $invoice->nama_pj,
                                'Jabatan' => $invoice->jabatan_pj
                            ]),
                            'created_at' => Carbon::now(),
                            'created_by' => $this->karyawan,
                        ];
    
                        DB::table('qr_documents')->insert($dataQr);
                        self::generatePDF($request->no_invoice);
                    }

                    $tokenService = new GenerateToken();
                    if($invoice->upload_file != null) {
                        $invoice->filename = $invoice->upload_file;
                    }
                    $token = $tokenService->save('INVOICE', $invoice, $this->karyawan, 'invoice');
                    // dd('masuk');

                    Invoice::where('no_invoice', $request->no_invoice)->update([
                        'id_token' => $token->id,
                        'is_generate' => 1,
                        'generated_at' => Carbon::now(),
                        'generated_by' => $this->karyawan
                    ]);
                }


                $message = "Invoice number $invoice->no_invoice success Generated";
                DB::commit();
                return response()->json(['message' => $message, 'status' => 200], 200);
            } catch (\Throwable $th) {
                DB::rollback();
                dd($th);
                return response()->json(['message' => $th->getMessage()], 401);
            }
        } else {
            DB::rollback();
            return response()->json(['message' => 'Data not Found.!'], 401);
        }
    }

    public function getCustom(Request $request)
    {
        $dataHead = Invoice::with('orderHeaderQuot')->where('is_active', true)
            ->where('no_invoice', $request->no_invoice)
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
            $dataDecoded = json_decode($dataHead->custom_invoice);

            $dataReturn = $dataDecoded->data;
            $hargaReturn = $dataDecoded->harga;
            // dd($hargaReturn);
            return response()->json([
                'data' => $dataReturn,
                'harga' => $hargaReturn,
                'dataHead' => $dataHead,
            ], 200);
        } else {

            $getDetailQt = Invoice::select('no_quotation', 'periode')
                ->where('is_active', true)
                ->where('no_invoice', $request->no_invoice)
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
                            ->where('no_invoice', $request->no_invoice)
                            ->where('quot_h.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('invoice.no_quotation', $value->no_quotation)
                            ->where('quot_d.periode_kontrak', $value->periode)
                            ->orderBy('invoice.no_order')
                            ->first();

                        $hargaDetail = Invoice::select(DB::raw('SUM(quot_d.total_discount) AS diskon, SUM(quot_d.total_ppn) AS ppn, SUM(quot_d.grand_total) AS sub_total, SUM(quot_d.total_pph) AS pph, SUM(quot_d.biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(invoice.piutang) AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_d.total_discount_transport, quot_d.biaya_di_luar_pajak, quot_d.total_discount_perdiem'))
                            ->leftJoin('request_quotation_kontrak_H AS quot_h', 'invoice.no_quotation', '=', 'quot_h.no_document')
                            ->leftJoin('request_quotation_kontrak_D AS quot_d', 'quot_h.id', '=', 'quot_d.id_request_quotation_kontrak_h')
                            ->where('no_invoice', $request->no_invoice)
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
                            ->where('no_invoice', $request->no_invoice)
                            ->where('quot.is_active', true)
                            ->where('invoice.is_active', true)
                            ->where('invoice.no_quotation', $value->no_quotation)
                            ->orderBy('invoice.no_order')
                            ->first();

                        $hargaDetail = Invoice::select(DB::raw('quot_h.total_discount AS diskon, quot_h.total_ppn AS ppn, quot_h.grand_total AS sub_total, quot_h.total_pph AS pph, quot_h.biaya_akhir AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, invoice.piutang AS sisa_tagihan, invoice.keterangan, SUM(invoice.total_tagihan) AS total_tagihan, quot_h.total_discount_transport, quot_h.biaya_diluar_pajak AS biaya_di_luar_pajak, quot_h.total_discount_perdiem'))
                            ->leftJoin('request_quotation_kontrak_H AS quot_h', 'invoice.no_quotation', '=', 'quot_h.no_document')
                            ->where('no_invoice', $request->no_invoice)
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
                        ->where('no_invoice', $request->no_invoice)
                        ->where('invoice.no_quotation', $value->no_quotation)
                        ->where('quot.is_active', true)
                        ->where('invoice.is_active', true)
                        ->orderBy('invoice.no_order')
                        ->first();

                    $hargaDetail = Invoice::select(DB::raw('SUM(total_discount) AS diskon, SUM(total_ppn) AS ppn, SUM(grand_total) AS sub_total, SUM(total_pph) AS pph, SUM(biaya_akhir) AS total_harga, SUM(nilai_tagihan) AS nilai_tagihan, SUM(piutang) AS sisa_tagihan, keterangan, SUM(invoice.total_tagihan) AS total_tagihan, total_discount_transport, biaya_di_luar_pajak, total_discount_perdiem'))
                        ->where('no_invoice', $request->no_invoice)
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
                return [
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

            // dd($summary);
            return response()->json([
                'data' => $dataReturn,
                'harga' => $hargaReturn,
                'dataHead' => $dataHead,
            ], 200);
        }
    }

    function rupiah($angka)
    {
        return "Rp " . number_format($angka, 0, '.', ',');
    }

    public function customInvoice(Request $request)
    {
        DB::beginTransaction();
        try {
            $updatedRows = Invoice::where('is_active', true)
                ->where('no_invoice', $request->no_invoice)
                ->update([
                    'is_custom' => true,
                    'custom_invoice' => json_encode([
                        'data' => $request->data,
                        'harga' => $request->harga
                    ]),
                ]);
            // $filename = \str_replace("/", "_", $request->no_invoice);
            //     $path = public_path() . "/qr_documents/" . $filename . '.svg';
            //     $link = 'https://www.intilab.com/validation/';
            //     $unique = 'isldc' . (int)floor(microtime(true) * 1000);
            // $dataQr = [
            //     'type_document' => 'invoice',
            //     'kode_qr' => $unique,
            //     'file' => $filename,
            //     'data' => json_encode([
            //         'no_document' => $request->no_invoice,
            //         'nama_customer' => $request->data[0]['nama_perusahaan'],
            //         'type_document' => 'invoice'
            //     ]),
            //     'created_at' => DATE('Y-m-d H:i:s'),
            //     'created_by' => $this->karyawan,
            // ];

            // DB::table('qr_documents')->insert($dataQr);

            self::generatePDF($request->no_invoice);
            DB::commit();
            return response()->json(['message' => 'Successfully Custom Invoice', 'status' => 200], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function uploadFile(Request $request)
    {
        Invoice::where('no_invoice', $request->no_invoice)->update([
            'is_emailed' => true
        ]);

        return response()->json([
            'message' => 'Successfully Approve Invoice',
            'status' => 200
        ], 200);
    }

    public function uploadFile(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $inv = Invoice::where('no_invoice', $request->no_invoice)->first();
            // Pastikan folder invoice ada
            $folder = public_path('invoice');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate nama file unik
            $fileName = 'INVOICE' . '_' . preg_replace('/\\//', '_', $inv->no_invoice) . '_' . 'upload' . '.pdf';

            // Simpan file
            $file->move($folder, $fileName);
            $inv->upload_file = $fileName;
            $inv->save();

            DB::commit();
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function uploadFilePph(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
                return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            }

            $inv = Invoice::where('no_invoice', $request->no_invoice)->first();
            // Pastikan folder invoice ada
            $folder = public_path('invoice-pph');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate nama file unik
            $fileName = 'PPH' . '_' . preg_replace('/\\//', '_', $inv->no_invoice) . '.pdf';

            // Simpan file
            $file->move($folder, $fileName);
            $inv->file_pph = $fileName;
            $inv->save();

            DB::commit();
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
