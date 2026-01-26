<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Datatables;
use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use App\Models\Invoice;
use App\Models\FollowupBilling;
use Carbon\CarbonPeriod;

class RekapBillingsController extends Controller
{
    public function index(Request $request)
    {
        $start = Carbon::now()->subMonth()->startOfMonth();
        $end   = Carbon::now()->addMonths(4)->endOfMonth();

        // ========= 1) BASE: PER INVOICE (query lo, tapi dibikin builder DB biar gampang fromSub) =========
        $perInvoice = DB::table('invoice')
            ->select([
                'invoice.no_invoice',
                DB::raw('COALESCE(MAX(order_header.nama_perusahaan), MAX(order_header.konsultan)) AS nama_customer'),
                DB::raw('MAX(order_header.konsultan) AS consultant'),

                DB::raw('FLOOR(MAX(invoice.nilai_tagihan)) AS nilai_tagihan'),
                DB::raw('DATE(MAX(invoice.tgl_jatuh_tempo)) AS tgl_jatuh_tempo'),

                DB::raw('(COALESCE(MAX(invoice.nilai_pelunasan),0) + COALESCE(SUM(withdraw.nilai_pembayaran),0)) AS nilai_pelunasan'),
                DB::raw('MAX(invoice.tgl_pelunasan) AS tgl_pelunasan'),
                DB::raw('MAX(invoice.keterangan) AS keterangan'),
            ])
            ->leftJoin('order_header', 'invoice.no_order', '=', 'order_header.no_order')
            ->leftJoin('withdraw', 'invoice.no_invoice', '=', 'withdraw.no_invoice')
            ->where('invoice.is_active', true)
            ->where('invoice.is_whitelist', false)
            ->whereBetween('invoice.tgl_jatuh_tempo', [$start->toDateString(), $end->toDateString()])
            ->groupBy('invoice.no_invoice')
            ->havingRaw('FLOOR(MAX(invoice.nilai_tagihan)) > (COALESCE(MAX(invoice.nilai_pelunasan),0) + COALESCE(SUM(withdraw.nilai_pembayaran),0))');

        // ========= 2) REKAP HARIAN dari subquery per-invoice =========
        // Output: 1 row per tanggal (yang ada datanya)
        $dailyRows = DB::query()
            ->fromSub($perInvoice, 't')
            ->groupBy('t.tgl_jatuh_tempo', DB::raw("DATE_FORMAT(t.tgl_jatuh_tempo, '%Y-%m')"))
            ->select([
                DB::raw("DATE_FORMAT(t.tgl_jatuh_tempo, '%Y-%m') AS bulan_key"),
                DB::raw("t.tgl_jatuh_tempo AS tanggal"),
                DB::raw('COUNT(*) AS total_invoice'),
                DB::raw('SUM(t.nilai_tagihan) AS total_tagihan'),
                DB::raw('SUM(t.nilai_pelunasan) AS total_pelunasan'),
                DB::raw('SUM(t.nilai_tagihan - t.nilai_pelunasan) AS total_outstanding'),
            ])
            ->orderBy('tanggal')
            ->get()
            ->keyBy('tanggal'); // gampang buat isi kalender

        // ========= 3) Isi semua tanggal di range biar "rekap harian" lengkap (tanggal kosong jadi 0) =========
        $period = CarbonPeriod::create($start->toDateString(), $end->toDateString());

        $dailyAll = collect();
        foreach ($period as $d) {
            $dateStr = $d->toDateString();
            $bulanKey = $d->format('Y-m');

            $row = $dailyRows[$dateStr] ?? null;

            $dailyAll->push([
                'bulan_key' => $bulanKey,
                'tanggal' => $dateStr,
                'total_invoice' => (int) ($row->total_invoice ?? 0),
                'total_tagihan' => (float) ($row->total_tagihan ?? 0),
                'total_pelunasan' => (float) ($row->total_pelunasan ?? 0),
                'total_outstanding' => (float) ($row->total_outstanding ?? 0),
            ]);
        }

        // ========= 4) Kelompokkan ke bulan-bulan (buat card) + total bulan =========
        $months = $dailyAll
            ->groupBy('bulan_key')
            ->map(function ($days, $bulanKey) {
                $bulanLabel = Carbon::createFromFormat('Y-m', $bulanKey)->translatedFormat('F Y');

                return [
                    'bulan_key' => $bulanKey,
                    'bulan_label' => $bulanLabel,

                    // total untuk card (sum dari harian)
                    'total_invoice' => $days->sum('total_invoice'),
                    'total_tagihan' => $days->sum('total_tagihan'),
                    'total_pelunasan' => $days->sum('total_pelunasan'),
                    'total_outstanding' => $days->sum('total_outstanding'),

                    // detail harian untuk table/graph di dalam card
                    'days' => $days->values(),
                ];
            })
            ->values();

        return response()->json([
            'range' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'months' => $months,
        ]);
    }
}
