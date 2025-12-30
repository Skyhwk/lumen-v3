<?php  
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{OrderHeader,MasterKaryawan,GenerateLink,Invoice,RecordPembayaranInvoice,Withdraw};
use App\Services\{GenerateToken,RenderInvoice,SendEmail,GetAtasan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;

class DistribusiInvoiceController extends Controller
{
    public function index(Request $request)
    {
        // 1. Subquery Withdraw
        $withdrawSub = DB::table('withdraw')
            ->select('no_invoice', DB::raw('SUM(nilai_pembayaran) as total_pembayaran'))
            ->groupBy('no_invoice');

        // 2. Subquery Invoice Aggregate
        $invoiceAgg = DB::table('invoice')
            ->select(
                DB::raw('MAX(id) AS id'),
                'no_invoice',
                DB::raw('SUM(total_tagihan) AS total_tagihan'),
                DB::raw('FLOOR(SUM(nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('MAX(nilai_pelunasan) AS nilai_pelunasan'),
                DB::raw('MAX(nama_pj) AS nama_pj'),
                DB::raw('MAX(no_po) AS no_po'),
                DB::raw('MAX(no_spk) AS no_spk'),
                DB::raw('MAX(created_at) AS created_at'),
                DB::raw('MAX(created_by) AS created_by'),
                DB::raw('MAX(tgl_jatuh_tempo) AS tgl_jatuh_tempo'),
                DB::raw('MAX(tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('GROUP_CONCAT(DISTINCT no_order) AS no_orders'),
                DB::raw('MAX(no_order) AS primary_no_order')
            )
            ->where('is_active', true)
            ->where('is_whitelist', false)
            ->groupBy('no_invoice');

        // 3. Main Query (PURE DATA)
        $query = DB::table(DB::raw("({$invoiceAgg->toSql()}) as inv"))
            ->mergeBindings($invoiceAgg)
            ->select(
                'inv.no_invoice',
                'inv.nilai_tagihan',
                'inv.total_tagihan',
                // Perbaikan logic sisa bayar & total bayar handle NULL
                DB::raw('(inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0)) AS total_pembayaran'),
                DB::raw('(inv.nilai_tagihan - (inv.nilai_pelunasan + COALESCE(w.total_pembayaran, 0))) AS sisa_tagihan'), 
                'inv.nama_pj',
                'inv.no_po',
                'inv.no_spk',
                DB::raw('COALESCE(oh.nama_perusahaan, oh.konsultan) AS nama_customer'),
                'oh.konsultan', // Tambahkan ini agar filterColumn konsultan jalan
                'inv.tgl_jatuh_tempo',
                'inv.tgl_pelunasan',
                'inv.no_orders',
                'inv.id'
            )
            ->leftJoin('order_header as oh', 'inv.primary_no_order', '=', 'oh.no_order')
            // PERBAIKAN 1: Gunakan leftJoinSub agar invoice yang belum dibayar tetap muncul
            ->leftJoinSub($withdrawSub, 'w', function($join) {
                $join->on('inv.no_invoice', '=', 'w.no_invoice');
            });

        // 4. Filter Kategori (Applied ke Query Builder, BUKAN Collection)
        if ($request->has('kategori')) {
            if ($request->kategori == 'big') {
                $query->where('inv.nilai_tagihan', '>', 5000000);
            } elseif ($request->kategori == 'small') {
                $query->where('inv.nilai_tagihan', '<=', 5000000);
            }
        }

        // 5. Return DataTables (Server Side)
        // PERBAIKAN 2: Gunakan $query langsung, jangan di ->get()
        return datatables()->of($query)
            ->filterColumn('no_invoice', function($query, $keyword) {
                $query->where('inv.no_invoice', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_customer', function($query, $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('oh.nama_perusahaan', 'like', "%{$keyword}%")
                    ->orWhere('oh.konsultan', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('nama_pj', function($query, $keyword) {
                $query->where('inv.nama_pj', 'like', "%{$keyword}%");
            })
            ->filterColumn('no_po', function($query, $keyword) {
                $query->where('inv.no_po', 'like', "%{$keyword}%");
            })
            ->filterColumn('konsultan', function($query, $keyword) {
                $query->where('oh.konsultan', 'like', "%{$keyword}%");
            })
            ->addColumn('withdraw_status', function($invoice) {
                $total = number_format($invoice->total_pembayaran, 0, ',', '.');
                return "Total Bayar: Rp " . $total; 
            })
            ->addColumn('sisa_tagihan_formatted', function($invoice) {
                return number_format($invoice->sisa_tagihan, 0, ',', '.');
            })
            ->rawColumns(['withdraw_status'])
            ->make(true);
    }
}