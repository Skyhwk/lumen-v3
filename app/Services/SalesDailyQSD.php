<?php
namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Crypto;

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
        self::handle($currentYear);
    }

    private static function handle($currentYear)
    {
        Log::info('[SalesDailyQSD] Starting QSD data update...');

        $maxDate = Carbon::create($currentYear, 12, 1)->endOfMonth()->format('Y-m-d');
        $nextYear = Carbon::create($currentYear, 12, 1)->addYear(2)->endOfMonth()->format('Y');
        $maxDateNextYear = Carbon::create($nextYear, 12, 1)->endOfMonth()->format('Y-m-d');
        for ($i = $currentYear; $i <= $nextYear; $i++) {
            $arrayYears[] = $i;
        }
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
                GROUP_CONCAT(
                    DISTINCT order_detail.kategori_1 
                    ORDER BY order_detail.kategori_1 
                    SEPARATOR ", "
                ) AS status_sampling,
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
            ->whereDate('order_detail.tanggal_sampling', '<=', $maxDateNextYear);
            // ->whereRaw("YEAR(order_detail.tanggal_sampling) = ?", [$currentYear]);

        // JOIN KONTRAK
        $rekapOrder->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rqkh.no_document')
                ->whereNotIn('rqkh.pelanggan_ID', ['SAIR02', 'T2PE01'])
                ->where('rqkh.is_active', true);
        });

        $rekapOrder->leftJoin('request_quotation_kontrak_D as rqkd', function ($join) use ($arrayYears) {
            $join->on('rqkh.id', '=', 'rqkd.id_request_quotation_kontrak_H')
                ->whereIn(DB::raw('LEFT(rqkd.periode_kontrak, 4)'), $arrayYears);
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
        $rekapOrder->where(function ($q) use ($arrayYears) {
            $q->where(function ($x) use ($arrayYears) {
                $x->where('order_detail.kontrak', 'C')
                    ->whereNotNull('rqkh.id')
                    ->whereColumn('order_detail.periode', 'rqkd.periode_kontrak')
                    ->whereNotNull('rqkd.id')
                    ->whereIn(DB::raw('LEFT(rqkd.periode_kontrak, 4)'), $arrayYears);
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
            order_detail.periode,
            order_detail.kontrak,
            CASE WHEN order_detail.kontrak="C" THEN rqkd.periode_kontrak ELSE NULL END
        ');

        /**
         * =====================================================
         * AMBIL DATA NON PENGUJIAN
         * =====================================================
         */

        $rekapOrderNonPengujian = DB::table('order_header as oh')
        ->join('request_quotation as rq', 'oh.no_document', '=', 'rq.no_document')
        ->join('master_karyawan as mk', 'rq.sales_id', '=', 'mk.id')
            ->where('oh.is_active', 1)
            ->whereIn(DB::raw('LEFT(oh.tanggal_order, 4)'), $arrayYears)
            ->whereNotIn('oh.id_pelanggan', ['SAIR02', 'T2PE01'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_detail as od')
                    ->whereRaw('od.id_order_header = oh.id')
                    ->where('od.is_active', 1);
            })
            ->selectRaw('
                oh.no_order,                                 -- 1
                oh.no_document AS no_quotation,              -- 2
                0 AS total_cfr,                              -- 3
                oh.nama_perusahaan,                          -- 4
                oh.konsultan,                                -- 5
                "Non Pengujian" AS status_sampling,          -- 6
                NULL AS periode,                             -- 7
                "N" AS kontrak,                              -- 8

                NULL AS sales_id_kontrak,                    -- 9
                NULL AS sales_nama_kontrak,                  -- 10

                rq.sales_id AS sales_id_non_kontrak,         -- 11
                mk.nama_lengkap AS sales_nama_non_kontrak,   -- 12

                NULL AS total_discount_kontrak,              -- 13
                NULL AS total_ppn_kontrak,                   -- 14
                NULL AS total_pph_kontrak,                   -- 15
                NULL AS biaya_akhir_kontrak,                 -- 16
                NULL AS grand_total_kontrak,                 -- 17
                NULL AS total_revenue_kontrak,               -- 18

                rq.total_discount AS total_discount_non_kontrak, -- 19
                rq.total_ppn AS total_ppn_non_kontrak,           -- 20
                rq.total_pph AS total_pph_non_kontrak,           -- 21
                rq.biaya_akhir AS biaya_akhir_non_kontrak,       -- 22
                rq.grand_total AS grand_total_non_kontrak,       -- 23

                rq.pelanggan_ID AS pelanggan_id_kontrak,     -- 24
                rq.pelanggan_ID AS pelanggan_id_non_kontrak, -- 25

                (COALESCE(rq.biaya_akhir,0)
                + COALESCE(rq.total_pph,0)
                - COALESCE(rq.total_ppn,0)) AS total_revenue_non_kontrak, -- 26

                oh.tanggal_order AS tanggal_sampling_min     -- 27
            ');

        /**
         * =====================================================
         * STREAM DATA
         * =====================================================
         */
        $union = $rekapOrder->unionAll($rekapOrderNonPengujian);

        $rows = DB::query()
            ->fromSub($union, 'rekap')
            ->orderBy('tanggal_sampling_min', 'desc')
            ->orderBy('no_order', 'asc')
            ->cursor();

        /**
         * =====================================================
         * DELETE DATA YEAR
         * =====================================================
         */
        // DB::table('daily_qsd')
        //     ->whereIn(DB::raw('LEFT(tanggal_sampling_min, 4)'), $arrayYears)
        //     ->delete();

        // Log::info('[SalesDailyQSD] Old data deleted for year ' . implode(', ', $arrayYears));

        /**
         * =====================================================
         * LOAD ALL INVOICE ONCE
         * =====================================================
         */
        $quotationList = DB::table('order_detail')
            ->whereIn(DB::raw('LEFT(tanggal_sampling, 4)'), $arrayYears)
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

            [$noInvoice, $isLunas, $pelunasan, $nominal] = self::buildInvoiceInfo($invoices);



            $buffer[] = [
                'no_order'             => $row->no_order,
                'no_invoice'           => $noInvoice,
                'nilai_invoice'        => $nominal,
                'nilai_pembayaran'     => $pelunasan,
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
                'nilai_invoice' => $items->first()['nilai_invoice'],
                'nilai_pembayaran' => $items->sum('nilai_pembayaran'),
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
                'status_sampling' => $items->pluck('status_sampling')->unique()->implode(', '),

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
        
            DB::transaction(function () use ($result, $arrayYears, &$totalInserted) {
        
                $now = Carbon::now()->subHours(7);
        
                // ===== NORMALISASI & GENERATE ID =====
                $result = $result
                    ->filter(fn ($r) => !empty($r['no_order']))
                    ->map(function ($r) use ($now) {
        
                        $periodeKey = $r['periode'] ?? '__NULL__';
        
                        $r['uuid'] = (new Crypto())->encrypt(
                            trim($r['no_order']) . '|' . trim($periodeKey)
                        );
        
                        $r['updated_at'] = $now;
        
                        unset($r['created_at']); // biar tidak reset
        
                        return $r;
                    });
        
                // ===== SIMPAN KEY VALID (ID) =====
                $validIds = $result
                    ->pluck('uuid')
                    ->unique()
                    ->values()
                    ->toArray();
        
                // ===== UPSERT =====
                $result->chunk(500)->each(function ($chunk) use (&$totalInserted) {
        
                    DB::table('daily_qsd')->upsert(
                        $chunk->toArray(),
                        ['uuid'], // PRIMARY KEY
                        [
                            'no_order',
                            'periode',
                            'no_invoice',
                            'nilai_invoice',
                            'nilai_pembayaran',
                            'no_quotation',
                            'pelanggan_ID',
                            'nama_perusahaan',
                            'konsultan',
                            'kontrak',
                            'sales_id',
                            'sales_nama',
                            'status_sampling',
                            'total_discount',
                            'total_ppn',
                            'total_pph',
                            'biaya_akhir',
                            'grand_total',
                            'total_revenue',
                            'total_cfr',
                            'tanggal_sampling_min',
                            'is_lunas',
                            'updated_at',
                        ]
                    );
        
                    $totalInserted += $chunk->count();
                });
        
                // ===== DELETE DATA LAMA YANG TIDAK ADA =====
                if (!empty($validIds)) {
                    DB::table('daily_qsd')
                        ->whereIn(DB::raw('LEFT(tanggal_sampling_min, 4)'), $arrayYears)
                        ->whereNotIn('uuid', $validIds)
                        ->delete();
                }
            });

            DB::statement("
                UPDATE daily_qsd q
                JOIN (
                    SELECT
                        uuid,
                        ROW_NUMBER() OVER (
                            PARTITION BY pelanggan_ID
                            ORDER BY
                                COALESCE(tanggal_sampling_min, '9999-12-31'),
                                CAST(SUBSTRING(no_order, 7, 2) AS UNSIGNED),
                                CAST(SUBSTRING(no_order, 9, 2) AS UNSIGNED),
                                uuid
                        ) AS rn
                    FROM daily_qsd
                ) x ON x.uuid = q.uuid
                SET q.status_customer = IF(x.rn = 1, 'new', 'exist')
            ");
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
            return [null, false, null, null];
        }

        $noInvoice = [];
        $isLunas   = false;
        $nilaiInvoice = 0;
        $nilaiPelunasan = 0;
        foreach ($invoices as $inv) {
            $nominal =
                ($inv->recordPembayaran ? $inv->recordPembayaran->sum('nilai_pembayaran') : 0) +
                ($inv->recordWithdraw ? $inv->recordWithdraw->sum('nilai_pembayaran') : 0);

            $status = $nominal >= $inv->nilai_tagihan ? ' (Lunas)' : '';
            if ($status == ' (Lunas)') {
                $isLunas = true;
            }

            $noInvoice[] = $inv->no_invoice . $status;
            $nilaiPelunasan += $nominal;
            $nilaiInvoice += $inv->nilai_tagihan;
        }

        if ($nilaiPelunasan == 0) $nilaiPelunasan = null;

        return [implode(', ', $noInvoice), $isLunas, $nilaiPelunasan, $nilaiInvoice];
    }
}
