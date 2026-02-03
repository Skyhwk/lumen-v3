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
use App\Models\CustomInvoice;
use App\Services\RenderInvoice;
use App\Services\SendEmail;
use App\Services\GetAtasan;
use App\Services\InvoiceService;
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
        DB::beginTransaction();
        try {
            $invoices = Invoice::with('custom')
                ->where('no_invoice', $request->no_invoice)
                ->get();

            foreach ($invoices as $key => $item) {
                $customInvoice = $item->custom;

                if (!$customInvoice) continue;

                // pastikan old_nilai_tagihan itu array (kalau dari json)
                $oldNilai = json_decode($customInvoice->old_nilai_tagihan, true);
                $oldTotal = json_decode($customInvoice->old_total_tagihan, true);
                $oldPPN = json_decode($customInvoice->old_ppn);

                // dd($oldNilai, $oldTotal, $oldPPN);

                if (is_string($oldNilai)) {
                    $oldNilai = json_decode($oldNilai, true) ?? [];
                }

                if (!array_key_exists($key, $oldNilai)) continue;

                $item->nilai_tagihan = $oldNilai[$key];
                $item->ppn = $oldPPN[$key];
                $item->total_tagihan = $oldTotal[$key];
                $item->is_custom = false;
                $item->save();
            }
            CustomInvoice::where('no_invoice', $request->no_invoice)->delete();
            self::generatePDF($request->no_invoice);
            DB::commit();

            return response()->json([
                'message' => "Custom payroll has been rollback."
            ]);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
            ], 500);
        }
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
                            'type_document' => 'invoice',
                            'no_document' => $request->no_invoice,
                            'nama_customer' => $getDetail->nama_perusahaan,
                        ]),
                        'created_at' => Carbon::now(),
                        'created_by' => $this->karyawan,
                    ];

                    DB::table('qr_documents')->insert($dataQr);

                    $tokenService = new GenerateToken();
                    $token = $tokenService->save('INVOICE', $invoice, $this->karyawan, 'invoice');
                    // dd('masuk');

                    Invoice::where('no_invoice', $request->no_invoice)->update([
                        'id_token' => $token->id,
                        'is_generate' => 1,
                        'generated_at' => Carbon::now(),
                        'generated_by' => $this->karyawan
                    ]);
                }

                self::generatePDF($request->no_invoice);

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
        $dataCustom = InvoiceService::getKeterangan($request->no_invoice);
        return response()->json($dataCustom, 200);
    }

    function rupiah($angka)
    {
        return "Rp " . number_format($angka, 0, '.', ',');
    }

    public function customInvoice(Request $request)
    {
        // dd($request->harga['total_custom']);
        DB::beginTransaction();
        try {
            // dd($request->all());
            $invoice = Invoice::where('is_active', true)
                ->where('no_invoice', $request->no_invoice)
                ->get();

            $alreadyCustom = CustomInvoice::where('no_invoice', $request->no_invoice)->count() > 0;

            if ($alreadyCustom) {
                CustomInvoice::where('no_invoice', $request->no_invoice)->update([
                    'details' => json_encode($request->data),
                    'ppn' => data_get($request->harga, 'ppn', 0),
                    'pph' => data_get($request->harga, 'pph', 0),
                    'total' => data_get($request->harga, 'total', 0),
                    'diskon' => data_get($request->harga, 'diskon', 0),
                    'sub_total' => data_get($request->harga, 'sub_total', 0),
                    'total_pph' => data_get($request->harga, 'total_pph', 0),
                    'total_ppn' => data_get($request->harga, 'total_ppn', 0),
                    'total_harga' => data_get($request->harga, 'total_harga', 0),
                    'sisa_tagihan' => data_get($request->harga, 'sisa_tagihan', 0),
                    'total_custom' => data_get($request->harga, 'total_custom', 0),
                    'total_diskon' => data_get($request->harga, 'total_diskon', 0),
                    'nilai_tagihan' => data_get($request->harga, 'nilai_tagihan', 0),
                    'total_tagihan' => data_get($request->harga, 'total_tagihan', 0),

                ]);
            } else {
                CustomInvoice::insert([
                    'no_invoice' => $request->no_invoice,

                    // karena kolom details json, ini juga oke (boleh juga biarin array tapi nanti harus create(), bukan insert)
                    'details' => json_encode($request->data),

                    // ✅ JSON columns: encode dulu
                    'old_ppn' => json_encode($invoice->pluck('ppn')->toArray()),
                    'old_nilai_tagihan' => json_encode($invoice->pluck('nilai_tagihan')->toArray()),
                    'old_total_tagihan' => json_encode($invoice->pluck('total_tagihan')->toArray()),

                    // numbers
                    'ppn' => (float) data_get($request->harga, 'ppn', 0),
                    'pph' => (float) data_get($request->harga, 'pph', 0),
                    'total' => (float) data_get($request->harga, 'total', 0),
                    'diskon' => (float) data_get($request->harga, 'diskon', 0),
                    'sub_total' => (float) data_get($request->harga, 'sub_total', 0),
                    'total_pph' => (float) data_get($request->harga, 'total_pph', 0),
                    'total_ppn' => (float) data_get($request->harga, 'total_ppn', 0),
                    'total_harga' => (float) data_get($request->harga, 'total_harga', 0),
                    'sisa_tagihan' => (float) data_get($request->harga, 'sisa_tagihan', 0),
                    'total_custom' => (float) data_get($request->harga, 'total_custom', 0),
                    'total_diskon' => (float) data_get($request->harga, 'total_diskon', 0),
                    'nilai_tagihan' => (float) data_get($request->harga, 'nilai_tagihan', 0),
                    'total_tagihan' => (float) data_get($request->harga, 'total_tagihan', 0),
                ]);
            }

            Invoice::where('no_invoice', $request->no_invoice)->update([
                'is_custom' => true,
                'updated_at' => Carbon::now(),
                'updated_by' => $this->karyawan,
                'nilai_tagihan' => data_get($request->harga, 'nilai_tagihan', 0),
                'total_tagihan' => data_get($request->harga, 'total_tagihan', 0),
                'ppn' => data_get($request->harga, 'ppn', 0),
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
                'status' => 401,
                'line' => $th->getLine(),
            ], 401);
        }
    }

    public function approveInvoice(Request $request)
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
