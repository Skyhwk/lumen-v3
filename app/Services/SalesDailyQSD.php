<?php
namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesDailyQSD
{
    public static function run()
    {
        $now = Carbon::now();
        $currentYear = $now->format('Y');
        $nextYear = $now->copy()->addYear()->format('Y');
        $currentMonth = (int)$now->format('m');

        // Jika sudah memasuki bulan Desember (1 bulan terakhir dalam tahun berjalan), 
        // kalkulasikan juga untuk tahun depan
        if ($currentMonth === 12) {
            self::handle($currentYear);
            self::handle($nextYear);
        } else {
            self::handle($currentYear);
        }
    }

    private static function handle($currentYear)
    {
        Log::info('[SalesDailyQSD] Starting QSD data update...');

        // $currentYear = '2024';
        // $maxDate     = '2024-12-31';
        // Mendapatkan tanggal terakhir di bulan 12 (Desember) untuk tahun yang diparsing
        $maxDate = Carbon::create($currentYear, 12, 1)->endOfMonth()->format('Y-m-d');
        /**
         * =====================================================
         * BUILD QUERY QSD
         * =====================================================
         */
        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order,
                order_detail.no_quotation,
                COUNT(DISTINCT order_detail.cfr) AS total_cfr,
                order_detail.nama_perusahaan,
                order_detail.konsultan,
                order_detail.kategori_1 as status_sampling,
                MIN(CASE order_detail.kontrak WHEN "C" THEN rqkd.periode_kontrak ELSE NULL END) as periode,
                order_detail.kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkh.sales_id ELSE NULL END) as sales_id_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN mk_kontrak.nama_lengkap ELSE NULL END) as sales_nama_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.sales_id ELSE NULL END) as sales_id_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN mk_non_kontrak.nama_lengkap ELSE NULL END) as sales_nama_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_discount ELSE NULL END) as total_discount_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_ppn ELSE NULL END) as total_ppn_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.total_pph ELSE NULL END) as total_pph_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.biaya_akhir ELSE NULL END) as biaya_akhir_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkd.grand_total ELSE NULL END) as grand_total_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C"
                    THEN (COALESCE(rqkd.biaya_akhir,0)+COALESCE(rqkd.total_pph,0)-COALESCE(rqkd.total_ppn,0))
                    ELSE NULL END) as total_revenue_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_discount ELSE NULL END) as total_discount_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_ppn ELSE NULL END) as total_ppn_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_pph ELSE NULL END) as total_pph_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.biaya_akhir ELSE NULL END) as biaya_akhir_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.grand_total ELSE NULL END) as grand_total_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkh.pelanggan_ID ELSE NULL END) as pelanggan_id_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.pelanggan_ID ELSE NULL END) as pelanggan_id_non_kontrak,
                MAX(CASE WHEN order_detail.kontrak != "C"
                    THEN (COALESCE(rq.biaya_akhir,0)+COALESCE(rq.total_pph,0)-COALESCE(rq.total_ppn,0))
                    ELSE NULL END) as total_revenue_non_kontrak,
                MIN(order_detail.tanggal_sampling) as tanggal_sampling_min
            ')
            ->where('order_detail.is_active', true)
            ->whereDate('order_detail.tanggal_sampling', '<=', $maxDate)
            ->whereRaw("YEAR(order_detail.tanggal_sampling) = ?", [$currentYear]);

        // JOIN KONTRAK
        $rekapOrder->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rqkh.no_document')
                ->whereNotIn('rqkh.pelanggan_ID', ['SAIR02', 'T2PE01'])
                ->where('rqkh.is_active', true);
        });

        $rekapOrder->leftJoin('request_quotation_kontrak_D as rqkd', function ($join) use ($currentYear) {
            $join->on('rqkh.id', '=', 'rqkd.id_request_quotation_kontrak_H')
                ->whereRaw("LEFT(rqkd.periode_kontrak, 4) = ?", [$currentYear]);
        });

        $rekapOrder->leftJoin('master_karyawan as mk_kontrak', 'rqkh.sales_id', '=', 'mk_kontrak.id');

        // JOIN NON KONTRAK
        $rekapOrder->leftJoin('request_quotation as rq', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rq.no_document')
                ->whereNotIn('rq.pelanggan_ID', ['SAIR02', 'T2PE01'])
                ->where('rq.is_active', true);
        });

        $rekapOrder->leftJoin('master_karyawan as mk_non_kontrak', 'rq.sales_id', '=', 'mk_non_kontrak.id');

        // FILTER UTAMA
        $rekapOrder->where(function ($q) use ($currentYear) {
            $q->where(function ($x) use ($currentYear) {
                $x->where('order_detail.kontrak', 'C')
                    ->whereNotNull('rqkh.id')
                    ->whereColumn('order_detail.periode', 'rqkd.periode_kontrak')
                    ->whereNotNull('rqkd.id')
                    ->whereRaw("LEFT(rqkd.periode_kontrak,4)=?", [$currentYear]);
            })->orWhere(function ($x) {
                $x->where('order_detail.kontrak', '!=', 'C')
                    ->whereNotNull('rq.id');
            });
        });

        $rekapOrder->groupByRaw('
            order_detail.no_order,
            order_detail.no_quotation,
            order_detail.nama_perusahaan,
            order_detail.konsultan,
            order_detail.kategori_1,
            order_detail.periode,
            order_detail.kontrak,
            CASE WHEN order_detail.kontrak="C" THEN rqkd.periode_kontrak ELSE NULL END
        ');

        $rekapOrder->orderBy('tanggal_sampling_min', 'desc')
            ->orderBy('order_detail.no_order', 'asc');

        /**
         * =====================================================
         * STREAM DATA
         * =====================================================
         */
        $rows = $rekapOrder->cursor();

        /**
         * =====================================================
         * DELETE DATA YEAR
         * =====================================================
         */
        DB::table('daily_qsd')
            ->whereYear('tanggal_sampling_min', $currentYear)
            ->delete();

        Log::info('[SalesDailyQSD] Old data deleted for year ' . $currentYear);

        /**
         * =====================================================
         * LOAD ALL INVOICE ONCE
         * =====================================================
         */
        $quotationList = DB::table('order_detail')
            ->whereYear('tanggal_sampling', $currentYear)
            ->distinct()
            ->pluck('no_quotation');

        $invoiceMap = Invoice::with(['recordPembayaran', 'recordWithdraw'])
            ->whereIn('no_quotation', $quotationList)
            ->where('is_active', true)
            ->get()
            ->groupBy(fn($i) => $i->no_quotation . '|' . $i->periode);


        // Log::info('[SalesDailyQSD] Found ' . count($existingCustomerIDs) . ' existing customer IDs');

        /**
         * =====================================================
         * INSERT DATA
         * =====================================================
         */
        $buffer        = [];
        $bufferSize    = 500;
        $totalInserted = 0;

        foreach ($rows as $row) {

            $keyExact = $row->no_quotation . '|' . $row->periode;
            $keyAll   = $row->no_quotation . '|all';

            $invoices = collect();

            if ($row->kontrak === 'C') {
                $invoices = $invoiceMap[$keyExact] ?? $invoiceMap[$keyAll] ?? collect();
            } else {
                $invoices = $invoiceMap[$keyExact] ?? collect();
            }

            [$noInvoice, $isLunas] = self::buildInvoiceInfo($invoices);



            $buffer[] = [
                'no_order'             => $row->no_order,
                'no_invoice'           => $noInvoice,
                'is_lunas'             => $isLunas,
                'no_quotation'         => $row->no_quotation,
                'total_cfr'            => $row->total_cfr,
                'pelanggan_ID'         => $row->kontrak === 'C' ? $row->pelanggan_id_kontrak : $row->pelanggan_id_non_kontrak,
                'nama_perusahaan'      => $row->nama_perusahaan,
                'konsultan'            => $row->konsultan,
                'periode'              => $row->periode,
                'kontrak'              => $row->kontrak,
                'status_sampling'      => $row->status_sampling,
                'sales_id'             => $row->kontrak === 'C' ? $row->sales_id_kontrak : $row->sales_id_non_kontrak,
                'sales_nama'           => $row->kontrak === 'C' ? $row->sales_nama_kontrak : $row->sales_nama_non_kontrak,
                'total_discount'       => $row->kontrak === 'C' ? $row->total_discount_kontrak : $row->total_discount_non_kontrak,
                'total_ppn'            => $row->kontrak === 'C' ? $row->total_ppn_kontrak : $row->total_ppn_non_kontrak,
                'total_pph'            => $row->kontrak === 'C' ? $row->total_pph_kontrak : $row->total_pph_non_kontrak,
                'biaya_akhir'          => $row->kontrak === 'C' ? $row->biaya_akhir_kontrak : $row->biaya_akhir_non_kontrak,
                'grand_total'          => $row->kontrak === 'C' ? $row->grand_total_kontrak : $row->grand_total_non_kontrak,
                'total_revenue'        => $row->kontrak === 'C' ? $row->total_revenue_kontrak : $row->total_revenue_non_kontrak,
                'tanggal_sampling_min' => $row->tanggal_sampling_min,
                'created_at'           => Carbon::now()->subHours(7),
            ];
        }

        /**
         * =====================================================
         * GROUPING DATA BY INVOICE AND SUM TOTAL
         * =====================================================
         */
        $collection = collect($buffer);

        $withInvoice = $collection->filter(fn ($row) => !empty($row['no_invoice']));
        $withoutInvoice = $collection->filter(fn ($row) => empty($row['no_invoice']));

        $grouped = $withInvoice
        ->groupBy('no_invoice')
        ->map(function ($items) {
            return [
                // ====== KEY UTAMA ======
                'no_invoice' => $items->first()['no_invoice'], //ok

                // ====== AMBIL DATA MIN / FIRST ======
                'no_order'        => $items->min('no_order'), //ok
                'no_quotation'    => $items->first()['no_quotation'],
                'pelanggan_ID'    => $items->first()['pelanggan_ID'],
                'nama_perusahaan' => $items->first()['nama_perusahaan'],
                'konsultan'       => $items->first()['konsultan'],
                'periode'         => $items->min('periode'),
                'kontrak'         => $items->first()['kontrak'],
                'sales_id'        => $items->first()['sales_id'],
                'sales_nama'      => $items->first()['sales_nama'],
                'status_sampling'      => $items->first()['status_sampling'],

                // ====== SUM FIELD ======
                'total_discount' => $items->sum('total_discount'),
                'total_ppn'      => $items->sum('total_ppn'),
                'total_pph'      => $items->sum('total_pph'),
                'biaya_akhir'    => $items->sum('biaya_akhir'),
                'grand_total'    => $items->sum('grand_total'),
                'total_revenue'  => $items->sum('total_revenue'),
                'total_cfr'      => $items->sum('total_cfr'),

                // ====== TANGGAL ======
                'tanggal_sampling_min' => $items->min('tanggal_sampling_min'),

                // ====== FLAG ======
                'is_lunas'        => $items->contains('is_lunas', false) ? false : true,
                'created_at'      => $items->first()['created_at'],
            ];
        });

        $result = $grouped
        ->values()
        ->merge($withoutInvoice)
        ->values();// reset index

        if ($result->isNotEmpty()) {
            DB::disableQueryLog();
            DB::transaction(function () use ($result, &$totalInserted) {
                $result->chunk(500)->each(function ($chunk) use (&$totalInserted) {
                    DB::table('daily_qsd')->insert($chunk->toArray());
                    $totalInserted += $chunk->count();
                });
            });
        }

        Log::info('[SalesDailyQSD] Inserted ' . $totalInserted . ' rows');
        Log::info('[SalesDailyQSD] Completed successfully');

        return true;
    }

    /**
     * =====================================================
     * HELPER INVOICE
     * =====================================================
     */
    private static function buildInvoiceInfo($invoices)
    {
        if ($invoices->isEmpty()) {
            return [null, false];
        }

        $noInvoice = [];
        $isLunas   = false;

        foreach ($invoices as $inv) {
            $nominal =
            $inv->recordPembayaran->sum('nilai_pembayaran') +
            $inv->recordWithdraw->sum('nilai_pembayaran');

            $status = $nominal >= $inv->nilai_tagihan ? ' (Lunas)' : '';
            if ($status) {
                $isLunas = true;
            }

            $noInvoice[] = $inv->no_invoice . $status;
        }

        return [implode(', ', $noInvoice), $isLunas];
    }
}
