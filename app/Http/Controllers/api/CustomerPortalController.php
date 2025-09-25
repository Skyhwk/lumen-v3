<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Carbon\Carbon;

Carbon::setLocale('id');

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use App\Models\{
    MasterPelanggan,
    QuotationKontrakH,
    QuotationNonKontrak,
    Invoice,
    OrderHeader,
    OrderDetail,
    PurchaseOrder,
    Advertise
};

use App\Services\SendEmail;

class CustomerPortalController extends Controller
{
    // HOMEPAGE
    public function getCompanies(Request $request)
    {
        $companies = MasterPelanggan::whereIn('id_pelanggan', json_decode($request->id_pelanggan))
            ->where('is_active', true)
            ->orderBy('nama_pelanggan')
            ->latest()
            ->get();

        $ads = Advertise::where('is_active', true)
            ->where('expired_at', '>=', Carbon::now()->format('Y-m-d'))->latest()->pluck('filename')->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $companies,
            'ads' => $ads,
            'message' => 'Companies fetched successfully'
        ], 200);
    }

    // // COMPANY DETAIL
    // public function getCompany(Request $request)
    // {
    //     $idPelanggan = $request->id_pelanggan;

    //     $company = MasterPelanggan::where('id_pelanggan', $idPelanggan)
    //         ->where('is_active', true)
    //         ->firstOrFail();

    //     $totalQuotationsKontrak = QuotationKontrakH::where('pelanggan_ID', $idPelanggan)
    //         ->where('is_active', true)
    //         ->whereNotIn('flag_status', ['rejected', 'void'])
    //         ->count();

    //     $totalQuotationsNonKontrak = QuotationNonKontrak::where('pelanggan_ID', $idPelanggan)
    //         ->where('is_active', true)
    //         ->whereNotIn('flag_status', ['rejected', 'void'])
    //         ->count();

    //     $company->totalQuotations = $totalQuotationsKontrak + $totalQuotationsNonKontrak;

    //     $company->totalOrders = OrderDetail::with(['orderHeader'])
    //         ->whereHas('orderHeader', function ($query) use ($request) {
    //             $query->where([
    //                 'id_pelanggan' => $request->id_pelanggan,
    //                 'is_active' => true,
    //             ]);
    //         })
    //         ->where('is_active', 1)
    //         ->count();

    //     $no_order = OrderHeader::select('no_order')
    //         ->where('id_pelanggan', $idPelanggan)
    //         ->where('is_active', true);

    //     $totalLhpsAir       = DB::table('lhps_air_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsEmisi     = DB::table('lhps_emisi_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsEmisiC    = DB::table('lhps_emisic_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsGeteran   = DB::table('lhps_getaran_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsKebisingan = DB::table('lhps_kebisingan_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsLinkungan = DB::table('lhps_ling_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsMedanLM   = DB::table('lhps_medanlm_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsPencahayaan = DB::table('lhps_pencahayaan_header')->whereIn('no_order', $no_order)->count();
    //     $totalLhpsSinarUV   = DB::table('lhps_sinaruv_header')->whereIn('no_order', $no_order)->count();

    //     $company->totalLhps = $totalLhpsAir + $totalLhpsEmisi + $totalLhpsEmisiC +
    //         $totalLhpsGeteran + $totalLhpsKebisingan + $totalLhpsLinkungan +
    //         $totalLhpsMedanLM + $totalLhpsPencahayaan + $totalLhpsSinarUV;

    //     $company->totalInvoices = Invoice::where([
    //         'pelanggan_id' => $request->id_pelanggan,
    //         'rekening' => '4976688988',
    //         'is_active' => true
    //     ])->count();

    //     $company->totalPurchaseOrders = PurchaseOrder::where('id_pelanggan', $idPelanggan)
    //         ->where('is_active', true)
    //         ->count();

    //     return response()->json([
    //         'status'  => 'success',
    //         'data'    => $company,
    //         'message' => 'Company details fetched successfully'
    //     ], 200);
    // }

    // COMPANY DETAIL
    public function getCompany(Request $request)
    {
        $idPelanggan = $request->id_pelanggan;
    
        // Ambil data pelanggan dulu
        $company = MasterPelanggan::where('id_pelanggan', $idPelanggan)
            ->where('is_active', true)
            ->firstOrFail();
    
        // Ambil total quotations sekaligus
        $quotationKontrak = QuotationKontrakH::selectRaw("
                SUM(CASE WHEN flag_status NOT IN ('rejected', 'void') THEN 1 ELSE 0 END) AS total_quotation,
                SUM(CASE WHEN flag_status = 'ordered' THEN 1 ELSE 0 END) AS total_ordered
            ")
            ->where('pelanggan_ID', $idPelanggan)
            ->where('is_active', true)
            ->first();
    
        $quotationNonKontrak = QuotationNonKontrak::selectRaw("
                SUM(CASE WHEN flag_status NOT IN ('rejected', 'void') THEN 1 ELSE 0 END) AS total_quotation,
                SUM(CASE WHEN flag_status = 'ordered' THEN 1 ELSE 0 END) AS total_ordered
            ")
            ->where('pelanggan_ID', $idPelanggan)
            ->where('is_active', true)
            ->first();
    
        $company->totalQuotations = ($quotationKontrak->total_quotation ?? 0) + ($quotationNonKontrak->total_quotation ?? 0);
        $company->totalOrderRecap = ($quotationKontrak->total_ordered ?? 0) + ($quotationNonKontrak->total_ordered ?? 0);
    
        // Total Order dari Order Detail
        $company->totalOrders = OrderDetail::whereHas('orderHeader', function ($query) use ($idPelanggan) {
                $query->where('id_pelanggan', $idPelanggan)
                      ->where('is_active', true);
            })
            ->where('is_active', true)
            ->count();
    
        // List no_order dalam 1 array
        $noOrders = OrderHeader::where('id_pelanggan', $idPelanggan)
            ->where('is_active', true)
            ->pluck('no_order')
            ->toArray();
    
        // Hitung semua LHPS langsung
        $lhpsTables = [
            'lhps_air_header',
            'lhps_emisi_header',
            'lhps_emisic_header',
            'lhps_getaran_header',
            'lhps_kebisingan_header',
            'lhps_ling_header',
            'lhps_medanlm_header',
            'lhps_pencahayaan_header',
            'lhps_sinaruv_header'
        ];
    
        $totalLhps = 0;
        if (!empty($noOrders)) {
            foreach ($lhpsTables as $table) {
                $totalLhps += DB::table($table)
                    ->whereIn('no_order', $noOrders)
                    ->count();
            }
        }
    
        $company->totalLhps = $totalLhps;
    
        // Total invoice
        $company->totalInvoices = Invoice::where('pelanggan_id', $idPelanggan)
            ->where('rekening', '4976688988')
            ->where('is_active', true)
            ->count();
    
        // Total PO
        $company->totalPurchaseOrders = PurchaseOrder::where('id_pelanggan', $idPelanggan)
            ->where('is_active', true)
            ->count();
    
        return response()->json([
            'status'  => 'success',
            'data'    => $company,
            'message' => 'Company details fetched successfully'
        ], 200);
    } 

    public function getSingleCompany(Request $request)
    {
        $company = MasterPelanggan::where('id_pelanggan', $request->id_pelanggan)
            ->where('is_active', true)
            ->firstOrFail();

        if ($request->invoices) {
            $company->purchase_orders = PurchaseOrder::with('pelanggan')
                ->where([
                    'id_pelanggan' => $request->id_pelanggan,
                    'is_active' => true
                ])->latest()->get();

            $company->invoices = Invoice::with('perusahaan')
                ->where(['pelanggan_id' => $request->id_pelanggan, 'rekening' => '4976688988', 'is_active' => true])->latest()->get();
        }

        return response()->json([
            'status'  => 'success',
            'data'    => $company,
            'message' => 'Company details fetched successfully'
        ], 200);
    }

    // QUOTATIONS
    public function getQuotations(Request $request)
    {
        $query = collect([
            QuotationKontrakH::class,
            QuotationNonKontrak::class
        ])->flatMap(
            fn($model) =>
            $model::with('order')
                ->where([
                    'pelanggan_ID' => $request->id_pelanggan,
                    'is_active' => true,
                ])
                ->whereNotIn('flag_status', ['rejected', 'void'])
                ->latest()
                ->get()
        );

        if ($search = $request->input('search.value')) {
            $query = $query->filter(function ($item) use ($search) {
                $search = strtolower($search);

                $tanggal = Carbon::parse($item->tanggal_penawaran);
                $tanggalIndo = strtolower($tanggal->translatedFormat('d F Y'));
                $tanggalEng = strtolower($tanggal->locale('en')->isoFormat('DD MMMM YYYY'));

                return str_contains(strtolower($item->no_document), $search) ||
                    str_contains(strtolower($item->nama_perusahaan), $search) ||
                    str_contains($tanggalIndo, $search) ||
                    str_contains($tanggalEng, $search);
            })->values();
        }

        $total = $query->count();

        $data = $query->skip($request->start)->take($request->length);

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    // ORDER RECAP
    public function getOrderRecap(Request $request)
    {
        $query = collect([
            QuotationKontrakH::class,
            QuotationNonKontrak::class
        ])->flatMap(
            fn($model) =>
            $model::with(['order', 'order.persiapanSampel'])
                ->where([
                    'pelanggan_ID' => $request->id_pelanggan,
                    'is_active' => true,
                ])
                ->where('flag_status', 'ordered')
                ->latest()
                ->get()
        );

        if ($search = $request->input('search.value')) {
            $query = $query->filter(function ($item) use ($search) {
                $search = strtolower($search);

                $tanggal = Carbon::parse($item->order->tanggal_order);
                $tanggalIndo = strtolower($tanggal->translatedFormat('d F Y'));
                $tanggalEng = strtolower($tanggal->locale('en')->isoFormat('DD MMMM YYYY'));

                return str_contains(strtolower($item->order->no_order), $search) ||
                    str_contains(strtolower($item->no_document), $search) ||
                    str_contains(strtolower($item->nama_perusahaan), $search) ||
                    str_contains($tanggalIndo, $search) ||
                    str_contains($tanggalEng, $search);
            })->values();
        }

        $total = $query->count();

        $data = $query->skip($request->start)->take($request->length);

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    // ORDERS
    public function getOrders(Request $request)
    {
        $search = strtolower($request->input('search.value'));

        $rawQuery = OrderDetail::with(['orderHeader'])
            ->whereHas('orderHeader', function ($query) use ($request) {
                $query->where([
                    'id_pelanggan' => $request->id_pelanggan,
                    'is_active' => true,
                ]);
            })
            ->where('is_active', 1)
            ->latest()
            ->get();

        $filtered = $rawQuery->filter(function ($item) use ($search) {
            $samplingDateIndo = strtolower(Carbon::parse($item->tanggal_sampling)->translatedFormat('d F Y'));
            $samplingDateEng  = strtolower(Carbon::parse($item->tanggal_sampling)->locale('en')->isoFormat('DD MMMM YYYY'));

            return str_contains(strtolower($item->keterangan_1), $search) ||
                str_contains(strtolower($item->cfr), $search) ||
                str_contains(strtolower($item->no_sampel), $search) ||
                str_contains(strtolower($item->regulasi), $search) ||
                str_contains($samplingDateIndo, $search) ||
                str_contains($samplingDateEng, $search);
        })->values();

        $total = $filtered->count();

        $data = $filtered->slice($request->start, $request->length)->values();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    // LHPS
    public function getLHPs(Request $request)
    {
        $orderH = OrderHeader::with([
            'perusahaan:id_pelanggan,nama_pelanggan',
            'getLhpsAirHeader',
            'getLhpsEmisiHeader',
            'getLhpsEmisiCHeader',
            'getLhpsGeteranHeader',
            'getLhpsKebisinganHeader',
            'getLhpsLinkunganHeader',
            'getLhpsMedanLMHeader',
            'getLhpsPencahayaanHeader',
            'getLhpsSinarUVHeader'
        ])->where([
            'id_pelanggan' => $request->id_pelanggan,
            'is_active' => true,
        ])->latest()->get();

        $result = [];
        $headers = [
            'getLhpsAirHeader',
            'getLhpsEmisiHeader',
            'getLhpsEmisiCHeader',
            'getLhpsGeteranHeader',
            'getLhpsKebisinganHeader',
            'getLhpsLinkunganHeader',
            'getLhpsMedanLMHeader',
            'getLhpsPencahayaanHeader',
            'getLhpsSinarUVHeader'
        ];

        foreach ($orderH as $item) {
            foreach ($headers as $header) {
                if ($item->$header) {
                    foreach ($item->$header as $lhps) {
                        $result[] = [
                            'no_order' => $item->no_order,
                            'no_quotation' => $item->no_document,
                            'no_lhps' => $lhps->no_lhp,
                            'file' => $lhps->file_lhp,
                            'type' => $lhps->sub_kategori,
                            'perusahaan' => $item->perusahaan
                        ];
                    }
                }
            }
        }

        $result = collect($result);

        $search = strtolower($request->input('search.value'));

        $filtered = $result->filter(function ($item) use ($search) {
            return str_contains(strtolower($item['no_quotation']), strtolower($search)) ||
                str_contains(strtolower($item['no_order']), strtolower($search)) ||
                str_contains(strtolower($item['no_lhps']), strtolower($search)) ||
                str_contains(strtolower($item['type']), strtolower($search)) ||
                str_contains(strtolower($item['perusahaan']), strtolower($search));
        })->values();

        $total = $filtered->count();

        $data = $filtered->slice($request->start, $request->length)->values();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    // INVOICES
    public function getInvoices(Request $request)
    {
        $search = strtolower($request->input('search.value'));

        $rawQuery = Invoice::with('perusahaan')
            ->where([
                'pelanggan_id' => $request->id_pelanggan,
                'rekening' => '4976688988',
                'is_active' => true
            ])
            ->latest()
            ->get();

        $filtered = $rawQuery->filter(function ($item) use ($search) {
            $dueDateIndo = strtolower(Carbon::parse($item->tgl_jatuh_tempo)->translatedFormat('d F Y'));
            $dueDateEng  = strtolower(Carbon::parse($item->tgl_jatuh_tempo)->locale('en')->isoFormat('DD MMMM YYYY'));

            return str_contains(strtolower($item->no_invoice), $search) ||
                str_contains(strtolower($item->no_quotation), $search) ||
                str_contains(strtolower($item->no_po), $search) ||
                str_contains(strtolower($item->no_spk), $search) ||
                str_contains(strtolower($item->nilai_tagihan), $search) ||
                str_contains(strtolower($item->perusahaan->nama_pelanggan), $search) ||
                str_contains($dueDateIndo, $search) ||
                str_contains($dueDateEng, $search);
        })->values();

        $total = $filtered->count();

        $data = $filtered->slice($request->start, $request->length)->values();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    // PURCHASE ORDERS
    public function getPurchaseOrders(Request $request)
    {
        $search = strtolower($request->input('search.value'));

        $rawQuery = PurchaseOrder::with('pelanggan')
            ->where([
                'id_pelanggan' => $request->id_pelanggan,
                'is_active' => true
            ])
            ->latest()
            ->get();

        $filtered = $rawQuery->filter(function ($item) use ($search) {
            $createdIndo = strtolower(Carbon::parse($item->created_at)->translatedFormat('d F Y'));
            $createdEng  = strtolower(Carbon::parse($item->created_at)->locale('en')->isoFormat('DD MMMM YYYY'));

            return str_contains(strtolower($item->no_po), $search) ||
                str_contains(strtolower($item->invoice), $search) ||
                str_contains($createdIndo, $search) ||
                str_contains($createdEng, $search);
        })->values();

        $total = $filtered->count();

        $data = $filtered->slice($request->start, $request->length)->values();

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $data,
        ]);
    }

    public function storePurchaseOrders(Request $request)
    {
        $base64String = $request->filename;

        // Decode Base64 untuk mendapatkan mime type
        $fileData = base64_decode($base64String);
        $finfo = finfo_open();
        $mimeType = finfo_buffer($finfo, $fileData, FILEINFO_MIME_TYPE);
        finfo_close($finfo);

        // Mapping MIME type ke ekstensi file
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf'
        ];

        $extension = isset($mimeToExt[$mimeType]) ? $mimeToExt[$mimeType] : 'bin'; // Default ke .bin kalau ga ketemu

        $fileName = 'po_' . time() . '.' . $extension;
        $filePath = public_path('purchase_orders/' . $fileName);
        file_put_contents($filePath, $fileData);

        try {
            $po = new PurchaseOrder;
            $po->id_pelanggan = $request->id_pelanggan;
            $po->no_po = $request->no_po;
            $po->invoice = $request->invoices;
            $po->filename = $fileName;
            $po->save();

            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'Purchase order saved successfully'
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => $ex->getMessage()
            ], 400);
        }
    }

    public function updatePurchaseOrders(Request $request)
    {
        $existingInvoices = PurchaseOrder::where('id_pelanggan', $request->id_pelanggan)
            ->where('is_active', true)
            ->where('id', '!=', $request->id)
            ->pluck('invoice')
            ->flatMap(fn($json) => json_decode($json, true) ?? [])
            ->unique()
            ->values()
            ->all();

        $duplicates = array_intersect(json_decode($request->invoices), $existingInvoices);

        if (count($duplicates) > 0) {
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => 'These invoice numbers have already been used: ' . implode(', ', $duplicates)
            ], 400);
        }

        if ($request->filename) {
            $base64String = $request->filename;

            // Decode Base64 untuk mendapatkan mime type
            $fileData = base64_decode($base64String);
            $finfo = finfo_open();
            $mimeType = finfo_buffer($finfo, $fileData, FILEINFO_MIME_TYPE);
            finfo_close($finfo);

            // Mapping MIME type ke ekstensi file
            $mimeToExt = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'application/pdf' => 'pdf'
            ];

            $extension = isset($mimeToExt[$mimeType]) ? $mimeToExt[$mimeType] : 'bin'; // Default ke .bin kalau ga ketemu

            $fileName = 'po_' . time() . '.' . $extension;
            $filePath = public_path('purchase_orders/' . $fileName);
            file_put_contents($filePath, $fileData);
        }

        try {
            $po = PurchaseOrder::find($request->id);
            $po->id_pelanggan = $request->id_pelanggan;
            $po->no_po = $request->no_po;
            $po->invoice = $request->invoices;
            if ($request->filename) $po->filename = $fileName;
            $po->updated_at = date('Y-m-d H:i:s');
            $po->save();

            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'Purchase order saved successfully'
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => $ex->getMessage()
            ], 400);
        }
    }

    public function destroyPurchaseOrders(Request $request)
    {
        try {
            $po = PurchaseOrder::find($request->id);
            $po->deleted_at = date('Y-m-d H:i:s');
            $po->is_active = false;
            $po->save();

            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'Purchase order deleted successfully'
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'status' => 'error',
                'data' => [],
                'message' => $ex->getMessage()
            ], 400);
        }
    }














    // EMAILING PASSWORD RESET
    public function sendResetPasswordLink(Request $request)
    {
        $passwordResetToken = DB::connection('portal_customer')->table('password_reset_tokens');

        $passwordResetToken->where('email', $request->email)->delete();

        $token = Str::random(60);
        $passwordResetToken->insert([
            'email' => $request->email,
            'token' => Hash::make($token),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        SendEmail::where('to', $request->email)
            ->where('subject', 'Password reset request')
            ->where('body', '
                <div style="font-family: Arial, sans-serif; line-height: 1.6;">
                    <h2>Password Reset Request</h2>
                    <p>We received a request to reset the password for your account associated with this email address. If you made this request, please click the link below to reset your password:</p>
                    <div style="margin: 25px 0;">
                        <a href="' . env('PORTALV4') . "/customer/reset-password?token=$token&email=" . urlencode($request->email) . '" style="background-color: #0073e6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Masuk ke Portal</a>
                    </div>
                    <p>For security reasons, this link will expire in 60 minutes. If you did not request a password reset, please ignore this email.</p>
                    <p>If you need further assistance, please contact our support team.</p>
                    <p>Best regards,<br> Sales team</p>
                </div>')
            ->where('karyawan', env('MAIL_NOREPLY_USERNAME'))
            ->noReply()
            ->send();

        return response()->json([
            'status' => 'success',
            'data' => [],
            'message' => 'Reset link sent to your email'
        ], 200);
    }







    public function getBase64(Request $request)
    {
        $path = public_path($request->url);
        if (!file_exists($path)) return response()->json('File not found', 404);

        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'pdf'  => 'application/pdf',
            // dll
        ];

        $mime = $mimeTypes[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        return response()->json('data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)));
    }
}
