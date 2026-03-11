<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\DailyQsd;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RekapPiutangController extends Controller
{
    /**
     * Get rekap piutang per bulan dalam setahun
     * Dikelompokkan berdasarkan tanggal invoice
     */
    public function index(Request $request)
    {
        $year = $request->input('year', date('Y'));

        try {
            // Ambil seluruh invoice aktif dalam tahun terkait, dengan relasi pembayaran & withdraw
            $invoices = Invoice::with(['recordPembayaran', 'recordWithdraw'])
                ->selectRaw('no_invoice, SUM(nilai_tagihan) as nilai_tagihan, tgl_invoice, tgl_jatuh_tempo, COALESCE(MAX(nama_perusahaan)) AS nama_customer')
                ->whereYear('tgl_invoice', $year)
                ->where('is_active', true)
                ->groupBy('no_invoice', 'tgl_invoice', 'tgl_jatuh_tempo')
                ->get();

            // Inisialisasi struktur hasil untuk setiap bulan 1-12 (agar bulan tanpa invoice tetap muncul)
            $monthlySummary = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthlySummary[$m] = [
                    'month' => $m,
                    'total_tagihan' => 0.0,
                    'total_pembayaran' => 0.0,
                    'total_withdraw' => 0.0,
                    'total_piutang' => 0.0,
                    'count' => 0, // jumlah invoice yang belum bayar / piutang
                    'count_invoice' => 0, // total seluruh invoice bulan tsb (lunas + piutang)
                    'entries' => [] // daftar invoice outstanding untuk detail, jika ingin tampilkan
                ];
            }

            foreach ($invoices as $inv) {
                $month = (int)date('n', strtotime($inv->tgl_invoice));
                $nilai_tagihan = (float)$inv->nilai_tagihan;
                $nilai_pembayaran = (float)($inv->recordPembayaran->sum('nilai_pembayaran') ?? 0) + (float)($inv->recordWithdraw->sum('nilai_pembayaran') ?? 0);
                $nilai_withdraw = (float)($inv->recordWithdraw->sum('nilai_pembayaran') ?? 0);
                $piutang = max(0, floor($nilai_tagihan - $nilai_pembayaran));

                // increment total seluruh invoice pada bulan tsb (lunas maupun belum lunas)
                $monthlySummary[$month]['count_invoice'] += 1;

                // total value
                $monthlySummary[$month]['total_tagihan'] += $nilai_tagihan;
                $monthlySummary[$month]['total_pembayaran'] += $nilai_pembayaran;
                $monthlySummary[$month]['total_withdraw'] += $nilai_withdraw;

                // Invoice dengan piutang (belum lunas/ada sisa)
                if ($piutang > 0) {
                    $monthlySummary[$month]['total_piutang'] += $piutang;
                    $monthlySummary[$month]['count'] += 1;
                    $monthlySummary[$month]['entries'][] = [
                        'no_invoice' => $inv->no_invoice,
                        'nama_customer' => $inv->nama_customer,
                        'nilai_tagihan' => $nilai_tagihan,
                        'nilai_pembayaran' => $nilai_pembayaran,
                        'piutang' => $piutang,
                        'tgl_jatuh_tempo' => $inv->tgl_jatuh_tempo,
                        'tgl_invoice' => $inv->tgl_invoice,
                    ];
                }
            }

            // Siapkan data akhir (tanpa kunci "entries" jika tidak diperlukan)
            $result = [];
            foreach ($monthlySummary as $summary) {
                $persentase_piutang = 0;
                if ($summary['count_invoice'] > 0) {
                    $persentase_piutang = round(($summary['count'] / $summary['count_invoice']) * 100, 2);
                }
                $result[] = [
                    'month' => (int)$summary['month'],
                    'total_piutang' => (float)$summary['total_piutang'],
                    'count' => (int)$summary['count'], // jumlah invoice belum lunas
                    'count_invoice' => (int)$summary['count_invoice'],
                    'total_tagihan' => (float)$summary['total_tagihan'],
                    'total_pembayaran' => (float)$summary['total_pembayaran'],
                    'total_withdraw' => (float)$summary['total_withdraw'],
                    'entries' => $summary['entries'], // aktifkan jika ingin detail invoice per bulan
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

}
