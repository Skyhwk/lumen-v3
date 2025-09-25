<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

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
    PurchaseOrder
};

use App\Services\SendEmail;

class CustomerPortalAPIController extends Controller
{
    // HOMEPAGE
    public function getCompanies(Request $request)
    {
        $companies = MasterPelanggan::whereIn('id_pelanggan', json_decode($request->id_pelanggan))
            ->where('is_active', true)
            ->orderBy('nama_pelanggan')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $companies,
            'message' => 'Companies fetched successfully'
        ], 200);
    }

    // COMPANY DETAIL
    public function getCompany(Request $request)
    {
        $idPelanggan = $request->id_pelanggan;

        $company = MasterPelanggan::where('id_pelanggan', $idPelanggan)
            ->where('is_active', true)
            ->firstOrFail();

        $totalQuotationsKontrak = QuotationKontrakH::where('pelanggan_ID', $idPelanggan)
            ->where('is_active', true)
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->count();

        $totalQuotationsNonKontrak = QuotationNonKontrak::where('pelanggan_ID', $idPelanggan)
            ->where('is_active', true)
            ->whereNotIn('flag_status', ['rejected', 'void'])
            ->count();

        $company->totalQuotations = $totalQuotationsKontrak + $totalQuotationsNonKontrak;

        $company->totalOrders = OrderDetail::with(['orderHeader'])
            ->whereHas('orderHeader', function ($query) use ($request) {
                $query->where([
                    'id_pelanggan' => $request->id_pelanggan,
                    'is_active' => true,
                ]);
            })
            ->where('is_active', 1)
            ->count();

        $no_order = OrderHeader::select('no_order')
            ->where('id_pelanggan', $idPelanggan)
            ->where('is_active', true);

        $totalLhpsAir       = DB::table('lhps_air_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsEmisi     = DB::table('lhps_emisi_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsEmisiC    = DB::table('lhps_emisic_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsGeteran   = DB::table('lhps_getaran_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsKebisingan = DB::table('lhps_kebisingan_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsLinkungan = DB::table('lhps_ling_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsMedanLM   = DB::table('lhps_medanlm_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsPencahayaan = DB::table('lhps_pencahayaan_header')->whereIn('no_order', $no_order)->count();
        $totalLhpsSinarUV   = DB::table('lhps_sinaruv_header')->whereIn('no_order', $no_order)->count();

        $company->totalLhps = $totalLhpsAir + $totalLhpsEmisi + $totalLhpsEmisiC +
            $totalLhpsGeteran + $totalLhpsKebisingan + $totalLhpsLinkungan +
            $totalLhpsMedanLM + $totalLhpsPencahayaan + $totalLhpsSinarUV;

        $company->totalInvoices = Invoice::where(['pelanggan_id' => $idPelanggan, 'rekening' => '4976688988', 'is_active' => true])
            ->count();

        $company->totalPurchaseOrders = PurchaseOrder::where('id_pelanggan', $idPelanggan)
            ->where('is_active', true)
            ->count();

        return response()->json([
            'status'  => 'success',
            'data'    => $company,
            'message' => 'Company details fetched successfully'
        ], 200);
    }

    // QUOTATIONS
    public function getQuotations(Request $request)
    {
        $perPage = $request->per_page ?? 10; 
        $page = $request->page ?? 1; 
        $search = $request->search;

        $query = collect([
            QuotationKontrakH::class,
            QuotationNonKontrak::class
        ])->flatMap(function ($model) use ($request, $search) {
            $baseQuery = $model::select('id', 'nama_perusahaan', 'no_document', 'tanggal_penawaran', 'filename')
                ->where([
                    'pelanggan_ID' => $request->id_pelanggan,
                    'is_active' => true,
                ])
                ->whereNotIn('flag_status', ['rejected', 'void']);

            if ($search) {
                $baseQuery->where(function ($q) use ($search) {
                    $q->where('nama_perusahaan', 'like', "%{$search}%")
                    ->orWhere('no_document', 'like', "%{$search}%");
                });
            }

            return $baseQuery->latest()->get();
        });

        $paginated = $query->forPage($page, $perPage);
        $result = [
            'data' => $paginated->values(),
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $query->count(),
            'last_page' => ceil($query->count() / $perPage),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $result,
            'message' => 'Quotations fetched successfully'
        ], 200);
    }


    // ORDERS
    public function getOrders(Request $request)
    {
        
        try {
            $perPage = $request->per_page ?? 10; 
            $page = $request->page ?? 1; 
                
                if ($request->search) {
                    $orders = OrderDetail::with(['orderHeader'])
                    ->select('id','keterangan_1', 'cfr', 'regulasi')
                    ->whereHas('orderHeader', function ($query) use ($request) {
                        $query->where([
                            'id_pelanggan' => $request->id_pelanggan,
                            'is_active' => true,
                        ]);
                    })
                    ->where('is_active', 1)
                    ->where('keterangan_1', 'like', "%{$request->search}%")
                    ->orWhere('no_sampel', 'like', "%{$request->search}%")
                    ->latest()
                    ->paginate($perPage, ['*'], 'page', $page);
                } else {
                    $orders = OrderDetail::with(['orderHeader'])
                    ->select('id','keterangan_1', 'cfr', 'regulasi', 'no_quotation')
                    ->whereHas('orderHeader', function ($query) use ($request) {
                        $query->where([
                            'id_pelanggan' => $request->id_pelanggan,
                            'is_active' => true,
                        ]);
                    })
                    ->where('is_active', 1)
                    ->latest()
                    ->paginate($perPage, ['*'], 'page', $page);
                }
                
            return response()->json([
                'status' => 'success',
                'data' => [
                    'data' => $orders->items(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'has_more' => $orders->hasMorePages(),
                    'total' => $orders->total(),
                ],
                'message' => 'Sampling Points fetched successfully',
            ], 200);
        } catch (\Exception $th) {
            return response()->json([
                'status' => 'error',
                'data' => $th->getMessage(),
                'message' => 'Orders fetched successfully'
            ], 200);
        }
    }

    // LHPS
    public function getLHPs(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;
        $search = $request->search;

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

        $orders = OrderHeader::with(array_merge(['perusahaan:id_pelanggan,nama_pelanggan'], $headers))
            ->where([
                'id_pelanggan' => $request->id_pelanggan,
                'is_active' => true,
            ])
            ->latest()
            ->get();

        $result = [];

        foreach ($orders as $order) {
            foreach ($headers as $relation) {
                foreach ($order->$relation ?? [] as $lhp) {
                    $item = [
                        'no_order'     => $order->no_order,
                        'no_quotation' => $order->no_document,
                        'no_lhps'      => $lhp->no_lhp,
                        'file'         => $lhp->file_lhp,
                        'type'         => $lhp->sub_kategori,
                        'perusahaan'   => $order->perusahaan
                    ];

                    // Filter berdasarkan search kalau ada
                    if ($search) {
                        $searchLower = strtolower($search);
                        if (
                            str_contains(strtolower($lhp->no_lhp), $searchLower) ||
                            str_contains(strtolower($order->no_order), $searchLower) ||
                            str_contains(strtolower($lhp->sub_kategori), $searchLower)
                        ) {
                            $result[] = $item;
                        }
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }

        // Pagination manual
        $paginated = collect($result)->forPage($page, $perPage)->values();
        $total = count($result);

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $paginated,
                'total' => $total,
                'current_page' => (int) $page,
                'per_page' => (int) $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'message' => 'LHPs fetched successfully'
        ]);
    }


    // INVOICES
    public function getInvoices(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;
        $search = $request->search;

        $company = MasterPelanggan::where([
            'id_pelanggan' => $request->id_pelanggan,
            'is_active' => true,
        ])->firstOrFail();

        $invoicesQuery = Invoice::with('perusahaan:id_pelanggan,nama_pelanggan')
            ->select('id', 'no_invoice', 'pelanggan_id', 'keterangan', 'total_tagihan', 'tgl_jatuh_tempo', 'no_quotation', 'filename', 'no_po', 'no_spk')
            ->where([
                'pelanggan_id' => $request->id_pelanggan,
                'rekening' => '4976688988',
                'is_active' => true,
            ]);

        // Handle search
        if ($search) {
            $invoicesQuery->where(function ($query) use ($search) {
                $query->where('no_invoice', 'like', "%$search%")
                    ->orWhere('keterangan', 'like', "%$search%")
                    ->orWhere('total_tagihan', 'like', "%$search%");
            });
        }

        $invoices = $invoicesQuery->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $company->invoices = $invoices->items();

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $invoices->items(),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'last_page' => $invoices->lastPage(),
            ],
            'message' => 'Invoices fetched successfully'
        ], 200);
    }


    // PURCHASE ORDERS
    public function getPurchaseOrders(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $page = $request->page ?? 1;
        $search = $request->search;

        $poQuery = PurchaseOrder::with('pelanggan')
            ->where([
                'id_pelanggan' => $request->id_pelanggan,
                'is_active' => true,
            ]);

        // Search filter
        if ($search) {
            $poQuery->where(function ($q) use ($search) {
                $q->where('no_po', 'like', "%$search%")
                ->orWhere('keterangan', 'like', "%$search%");
            });
        }

        $poPaginated = $poQuery->latest()
            ->paginate($perPage, ['*'], 'page', $page);

        $usedInvoiceNumbers = collect($poPaginated->items())
            ->flatMap(function ($po) {
                $decoded = json_decode($po->invoice);
                return is_array($decoded) ? $decoded : [$decoded];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Ambil invoice yang belum dipakai di PO
        $availableInvoices = Invoice::selectRaw('distinct no_invoice')
            ->where([
                'pelanggan_id' => $request->id_pelanggan,
                'is_active' => true,
            ])
            ->whereNotIn('no_invoice', $usedInvoiceNumbers)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $poPaginated->items(),
                'extra_data' => [
                    'available_invoices' => $availableInvoices,
                ],
                'current_page' => $poPaginated->currentPage(),
                'last_page' => $poPaginated->lastPage(),
                'per_page' => $poPaginated->perPage(),
                'total' => $poPaginated->total(),
            ],
            'message' => 'Purchase Orders fetched successfully'
        ], 200);
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
