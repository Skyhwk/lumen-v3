<?php
namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Crypto;
use Illuminate\Support\Collection;

class SalesDailyQSD
{
    private const EXCLUDE_CUSTOMERS = ['SAIR02', 'T2PE01', 'TPTT01'];

    public static function run(): void
    {
        $now = Carbon::now();
        $currentYear = $now->format('Y');
        printf("\n[SchaduleUpdateQsd] [%s] Running untuk tahun %d\n", $now->format('Y-m-d H:i:s'), $currentYear);
        self::handle((int)$currentYear);
    }

    private static function handle(int $currentYear): bool
    {
        Log::info('[SchaduleUpdateQsd] Starting QSD data update...');
        $arrayYears = self::getYearRange($currentYear);

        $rekapOrder = self::buildQueryQsd($arrayYears);
        
        $rekapOrderNonPengujian = self::buildQueryNonPengujian($arrayYears);
        
        $rows = self::streamData($rekapOrder, $rekapOrderNonPengujian);
        
        // Invoice Mapping
        [$invoiceMap, $spesialInv, $noQTSpesial, $mapedInv, $groupedInvSpesial] = self::buildInvoiceMaps($arrayYears);
        // Buffering
        $buffer = self::bufferMapping($rows, $invoiceMap);
        
        // First Grouped/Grouped Data
        [$withInvoice, $groupedSpesial, $withoutInvoice, $firstGrouped, $grouped, $result] = self::processGroupings($buffer, $spesialInv, $mapedInv, $groupedInvSpesial, $noQTSpesial);
        // Simpan ke DB
        $totalInserted = 0;
        if ($result->isNotEmpty()) {
            printf("[SchaduleUpdateQsd] [%s] Inserting data to daily_qsd\n", Carbon::now()->format('Y-m-d H:i:s'));
            DB::disableQueryLog();
            DB::transaction(function () use ($result, $arrayYears, &$totalInserted) {
                $now = Carbon::now()->subHours(7);
                $result = $result
                    ->filter(fn ($r) => !empty($r['no_order']))
                    ->map(function ($r) use ($now) {
                        $periodeKey = $r['periode'] ?? '__NULL__';
                        $r['uuid'] = (new Crypto())->encrypt(trim($r['no_order']) . '|' . trim($periodeKey));
                        $r['updated_at'] = $now;
                        unset($r['created_at']);
                        return $r;
                    });
                $validIds = $result->pluck('uuid')->unique()->values()->toArray();
                $result->chunk(500)->each(function ($chunk) use (&$totalInserted) {
                    DB::table('daily_qsd')->upsert(
                        $chunk->toArray(), ['uuid'], [
                            'no_order', 
                            'periode', 
                            'no_invoice', 
                            'nilai_invoice', 
                            'nilai_pembayaran', 
                            'nilai_pengurangan',
                            'revenue_invoice',
                            'tanggal_pembayaran',
                            'no_po',
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
                            'updated_at']
                    );
                    $totalInserted += $chunk->count();
                });
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
                SELECT uuid, ROW_NUMBER() OVER (PARTITION BY pelanggan_ID
                ORDER BY COALESCE(tanggal_sampling_min, '9999-12-31'), CAST(SUBSTRING(no_order, 7, 2) AS UNSIGNED), CAST(SUBSTRING(no_order, 9, 2) AS UNSIGNED), uuid) AS rn
                FROM daily_qsd) x ON x.uuid = q.uuid
                SET q.status_customer = IF(x.rn = 1, 'new', 'exist')
                WHERE q.status_customer IS NULL
            ");

            DB::statement("
                UPDATE daily_qsd
                SET revenue_invoice = COALESCE(nilai_pembayaran, 0) - COALESCE(nilai_pengurangan, 0)
                WHERE COALESCE(nilai_pembayaran, 0) > 0
            ");

            // DB::statement("
            //     UPDATE daily_qsd
            //     SET tanggal_kelompok = CASE 
            //         -- jika tanggal_pembayaran NULL → pakai tanggal_sampling_min
            //         WHEN tanggal_pembayaran IS NULL THEN tanggal_sampling_min

            //         -- jika tanggal_pembayaran < tanggal_sampling_min → pakai tanggal_pembayaran
            //         WHEN STR_TO_DATE(
            //                 SUBSTRING_INDEX(tanggal_pembayaran, ',', 1),
            //                 '%Y-%m-%d'
            //             ) < tanggal_sampling_min
            //         THEN STR_TO_DATE(
            //                 SUBSTRING_INDEX(tanggal_pembayaran, ',', 1),
            //                 '%Y-%m-%d'
            //             )

            //         -- selain itu → tetap tanggal_sampling_min
            //         ELSE tanggal_sampling_min
            //     END
            //     WHERE tanggal_kelompok IS NULL
            // ");

            DB::statement("
                UPDATE daily_qsd
                SET tanggal_kelompok = CASE 
                    WHEN tanggal_pembayaran IS NULL THEN tanggal_sampling_min
                    WHEN STR_TO_DATE(SUBSTRING_INDEX(tanggal_pembayaran, ',', 1), '%Y-%m-%d') < tanggal_sampling_min
                        THEN STR_TO_DATE(SUBSTRING_INDEX(tanggal_pembayaran, ',', 1), '%Y-%m-%d')
                    ELSE tanggal_sampling_min
                END
                WHERE tanggal_kelompok IS NULL
                OR (
                        tanggal_pembayaran IS NULL
                        AND tanggal_kelompok <> tanggal_sampling_min
                    );
            ");

            printf("[SchaduleUpdateQsd] [%s] Updating daily_qsd completed", Carbon::now()->format('Y-m-d H:i:s'));
        }
        Log::info('[SchaduleUpdateQsd] Inserted ' . $totalInserted . ' rows');
        Log::info('[SchaduleUpdateQsd] Completed successfully');
        return true;
    }

    private static function getYearRange(int $currentYear): array
    {
        $nextYear = (int)Carbon::create($currentYear, 12, 1)->addYear(1)->endOfMonth()->format('Y');
        $arrayYears = [];
        for ($i = 2024; $i <= $nextYear; $i++) {
            $arrayYears[] = $i;
        }
        return $arrayYears;
    }

    private static function buildQueryQsd(array $arrayYears)
    {
        printf("[SchaduleUpdateQsd] [%s] Building query QSD for years %s\n", Carbon::now()->format('Y-m-d H:i:s'), implode(', ', $arrayYears));

        $maxDateNextYear = Carbon::create(end($arrayYears), 12, 1)->endOfMonth()->format('Y-m-d');
        $rekapOrder = DB::table('order_detail')
            ->selectRaw('
                order_detail.no_order, 
                order_detail.no_quotation, 
                COUNT(DISTINCT order_detail.cfr) AS total_cfr, 
                order_detail.nama_perusahaan, 
                order_detail.konsultan, 
                GROUP_CONCAT(DISTINCT order_detail.kategori_1 ORDER BY order_detail.kategori_1 SEPARATOR ", ") AS status_sampling, 
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
                MAX(CASE WHEN order_detail.kontrak = "C" THEN (COALESCE(rqkd.biaya_akhir,0)+COALESCE(rqkd.total_pph,0)-COALESCE(rqkd.total_ppn,0)) ELSE NULL END) as total_revenue_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_discount ELSE NULL END) as total_discount_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_ppn ELSE NULL END) as total_ppn_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.total_pph ELSE NULL END) as total_pph_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.biaya_akhir ELSE NULL END) as biaya_akhir_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.grand_total ELSE NULL END) as grand_total_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak = "C" THEN rqkh.pelanggan_ID ELSE NULL END) as pelanggan_id_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN rq.pelanggan_ID ELSE NULL END) as pelanggan_id_non_kontrak, 
                MAX(CASE WHEN order_detail.kontrak != "C" THEN (COALESCE(rq.biaya_akhir,0)+COALESCE(rq.total_pph,0)-COALESCE(rq.total_ppn,0)) ELSE NULL END) as total_revenue_non_kontrak, 
                MIN(order_detail.tanggal_sampling) as tanggal_sampling_min
            ')
            ->where('order_detail.is_active', true)
            ->whereDate('order_detail.tanggal_sampling', '<=', $maxDateNextYear);
        
        $rekapOrder->leftJoin('request_quotation_kontrak_H as rqkh', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rqkh.no_document')
                ->whereNotIn('rqkh.pelanggan_ID', self::EXCLUDE_CUSTOMERS)
                ->where('rqkh.is_active', true);
        });
        
        $rekapOrder->leftJoin('request_quotation_kontrak_D as rqkd', function ($join) use ($arrayYears) {
            $join->on('rqkh.id', '=', 'rqkd.id_request_quotation_kontrak_H')
                ->whereIn(DB::raw('LEFT(rqkd.periode_kontrak, 4)'), $arrayYears);
        });
        $rekapOrder->leftJoin('master_karyawan as mk_kontrak', 'rqkh.sales_id', '=', 'mk_kontrak.id');
        $rekapOrder->leftJoin('request_quotation as rq', function ($join) {
            $join->on('order_detail.no_quotation', '=', 'rq.no_document')
                ->whereNotIn('rq.pelanggan_ID', self::EXCLUDE_CUSTOMERS)
                ->where('rq.is_active', true);
        });
        $rekapOrder->leftJoin('master_karyawan as mk_non_kontrak', 'rq.sales_id', '=', 'mk_non_kontrak.id');
        $rekapOrder->where(function($q) use($arrayYears) {
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
        printf("[SchaduleUpdateQsd] [%s] Query QSD built successfully\n", Carbon::now()->format('Y-m-d H:i:s'));
        return $rekapOrder;
    }

    private static function baseNonPengujianQuery(array $arrayYears)
    {
        return DB::table('order_header as oh')
            ->where('oh.is_active', 1)
            ->whereIn(DB::raw('LEFT(oh.tanggal_order, 4)'), $arrayYears)
            ->whereNotIn('oh.id_pelanggan', self::EXCLUDE_CUSTOMERS)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_detail as od')
                    ->whereRaw('od.id_order_header = oh.id')
                    ->where('od.is_active', 1);
            });
    }

    private static function nonKontrakQuery(array $arrayYears)
    {
        return self::baseNonPengujianQuery($arrayYears)
            ->join('request_quotation as rq', 'oh.no_document', '=', 'rq.no_document')
            ->join('master_karyawan as mk', 'rq.sales_id', '=', 'mk.id')
            ->selectRaw('
                oh.no_order,
                oh.no_document AS no_quotation,
                0 AS total_cfr,
                oh.nama_perusahaan,
                oh.konsultan,
                "Non Pengujian" AS status_sampling,
                NULL AS periode,
                "N" AS kontrak,

                NULL AS sales_id_kontrak,
                NULL AS sales_nama_kontrak,

                rq.sales_id AS sales_id_non_kontrak,
                mk.nama_lengkap AS sales_nama_non_kontrak,

                NULL AS total_discount_kontrak,
                NULL AS total_ppn_kontrak,
                NULL AS total_pph_kontrak,
                NULL AS biaya_akhir_kontrak,
                NULL AS grand_total_kontrak,
                NULL AS total_revenue_kontrak,

                rq.total_discount AS total_discount_non_kontrak,
                rq.total_ppn AS total_ppn_non_kontrak,
                rq.total_pph AS total_pph_non_kontrak,
                rq.biaya_akhir AS biaya_akhir_non_kontrak,
                rq.grand_total AS grand_total_non_kontrak,

                rq.pelanggan_ID AS pelanggan_id_kontrak,
                rq.pelanggan_ID AS pelanggan_id_non_kontrak,

                (COALESCE(rq.biaya_akhir,0)
                    + COALESCE(rq.total_pph,0)
                    - COALESCE(rq.total_ppn,0)
                ) AS total_revenue_non_kontrak,

                CASE WHEN rq.tanggal_penawaran < oh.tanggal_order THEN rq.tanggal_penawaran ELSE oh.tanggal_order END AS tanggal_sampling_min
            ');
    }

    private static function kontrakQuery(array $arrayYears)
    {
        return self::baseNonPengujianQuery($arrayYears)
            ->join('request_quotation_kontrak_H as rq', 'oh.no_document', '=', 'rq.no_document')
            ->join('request_quotation_kontrak_D as rqkd', 'rq.id', '=', 'rqkd.id_request_quotation_kontrak_H')
            ->join('master_karyawan as mk', 'rq.sales_id', '=', 'mk.id')
            ->selectRaw('
                oh.no_order,
                oh.no_document AS no_quotation,
                0 AS total_cfr,
                oh.nama_perusahaan,
                oh.konsultan,
                "Non Pengujian" AS status_sampling,
                rqkd.periode_kontrak AS periode,
                "C" AS kontrak,

                rq.sales_id AS sales_id_kontrak,
                mk.nama_lengkap AS sales_nama_kontrak,

                NULL AS sales_id_non_kontrak,
                NULL AS sales_nama_non_kontrak,

                rqkd.total_discount AS total_discount_kontrak,
                rqkd.total_ppn AS total_ppn_kontrak,
                rqkd.total_pph AS total_pph_kontrak,
                rqkd.biaya_akhir AS biaya_akhir_kontrak,
                rqkd.grand_total AS grand_total_kontrak,

                (COALESCE(rqkd.biaya_akhir,0)
                    + COALESCE(rqkd.total_pph,0)
                    - COALESCE(rqkd.total_ppn,0)
                ) AS total_revenue_kontrak,

                NULL AS total_discount_non_kontrak,
                NULL AS total_ppn_non_kontrak,
                NULL AS total_pph_non_kontrak,
                NULL AS biaya_akhir_non_kontrak,
                NULL AS grand_total_non_kontrak,

                rq.pelanggan_ID AS pelanggan_id_kontrak,
                rq.pelanggan_ID AS pelanggan_id_non_kontrak,

                NULL AS total_revenue_non_kontrak,

                CASE WHEN rq.tanggal_penawaran < oh.tanggal_order THEN rq.tanggal_penawaran ELSE oh.tanggal_order END AS tanggal_sampling_min
            ');
    }

    private static function buildQueryNonPengujian(array $arrayYears)
    {
        return self::nonKontrakQuery($arrayYears)
            ->unionAll(self::kontrakQuery($arrayYears));
    }

    private static function streamData($rekapOrder, $rekapOrderNonPengujian)
    {
        $union = $rekapOrder->unionAll($rekapOrderNonPengujian);
        return DB::query()
            ->fromSub($union, 'rekap')
            ->orderBy('tanggal_sampling_min', 'desc')
            ->orderBy('no_order', 'asc')
            ->cursor();
    }

    private static function buildInvoiceMaps(array $arrayYears)
    {
        $quotationList = DB::table('order_detail')
            ->whereIn(DB::raw('LEFT(tanggal_sampling, 4)'), $arrayYears)
            ->distinct()
            ->pluck('no_quotation');
        
        $spesialQt = DB::table('order_header')->whereIn(DB::raw('LEFT(tanggal_order, 4)'), $arrayYears)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_detail as od')
                    ->whereRaw('od.id_order_header = order_header.id')
                    ->where('od.is_active', 1);
            })
            ->whereNotNull('no_document')
            ->distinct()
            ->pluck('no_document');

        $quotationList = $quotationList->merge($spesialQt);

        $excludeInv = Invoice::where('is_active', 1)
            ->whereIn('no_invoice', function ($q) {
                $q->select('no_invoice')
                    ->from('invoice')
                    ->where('is_active', 1)
                    ->groupBy('no_invoice')
                    ->havingRaw('COUNT(*) > 1')
                    ->havingRaw('COUNT(DISTINCT no_quotation) > 1');
            })
            ->groupBy('no_invoice')
            ->pluck('no_invoice');

        $invoiceMap = Invoice::with(['recordPembayaran', 'recordWithdraw'])
            ->whereIn('no_quotation', $quotationList)
            ->whereNotIn('no_invoice', $excludeInv)
            ->where('is_active', true)
            ->get()
            ->groupBy(fn($i) => $i->no_quotation . '|' . $i->periode);

        $spesialInv = Invoice::with(['recordPembayaran', 'recordWithdraw'])
            ->where('is_active', 1)
            ->whereIn('no_invoice', $excludeInv);

        $noQTSpesial = $spesialInv->pluck('no_quotation')->toArray();

        $mapedInv = $spesialInv->get()->groupBy(fn($i) => $i->no_quotation);

        $groupedInvSpesial = $spesialInv
            ->select(
                'no_invoice',
                DB::raw('SUM(nilai_tagihan) as nilai_tagihan')
            )
            ->groupBy('no_invoice')
            ->get()
            ->keyBy('no_invoice');

        return [$invoiceMap, $spesialInv, $noQTSpesial, $mapedInv, $groupedInvSpesial];
    }

    private static function bufferMapping($rows, $invoiceMap)
    {
        printf("[SchaduleUpdateQsd] [%s] Buffering mapping data\n", Carbon::now()->format('Y-m-d H:i:s'));
        $buffer = [];
        foreach ($rows as $row) {
            $keyExact = $row->no_quotation . '|' . $row->periode;
            $keyAll   = $row->no_quotation . '|all';
            $invoices = collect();
            if ($row->kontrak === 'C') {
                $invoices = $invoiceMap[$keyExact] ?? $invoiceMap[$keyAll] ?? collect();
            } else {
                $invoices = $invoiceMap[$keyExact] ?? collect();
            }
            [$noInvoice, $isLunas, $pelunasan, $nominal, $revenueInvoice, $pengurangan, $tanggalPembayaran, $po] = self::buildInvoiceInfo($invoices);

            $buffer[] = [
                'no_order'             => $row->no_order,
                'no_invoice'           => $noInvoice,
                'nilai_invoice'        => $nominal,
                'nilai_pembayaran'     => $pelunasan,
                'nilai_pengurangan'    => $pengurangan,
                'revenue_invoice'      => $revenueInvoice,
                'tanggal_pembayaran'   => $tanggalPembayaran === '' ? null : $tanggalPembayaran,
                'no_po'                => $po === '' ? null : $po,
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
        printf("[SchaduleUpdateQsd] [%s] Buffering mapping data completed\n", Carbon::now()->format('Y-m-d H:i:s'));
        return $buffer;
    }

    private static function processGroupings($buffer, $spesialInv, $mapedInv, $groupedInvSpesial, $noQTSpesial)
    {
        printf("[SchaduleUpdateQsd] [%s] Processing groupings\n", Carbon::now()->format('Y-m-d H:i:s'));
        $collection = collect($buffer);
        $withInvoice = $collection->filter(fn ($row) => !empty($row['no_invoice']));
        $withoutInvoice = $collection->filter(fn ($row) => empty($row['no_invoice']));
        
        $groupedSpesial = $withoutInvoice
            ->filter(fn($item) => in_array($item['no_quotation'], $noQTSpesial))
            ->groupBy('no_quotation')
            ->map(function ($items) use ($mapedInv, $groupedInvSpesial) {
                $no_quotation = $items->first()['no_quotation'];
                
                $inv = isset($mapedInv[$no_quotation]) ? $mapedInv[$no_quotation]->first() : null;
                $no_inv = $inv->no_invoice ?? null;

                $nilai_invoice = $groupedInvSpesial[$no_inv]->nilai_tagihan ?? 0;

                $pembayaran = $groupedInvSpesial[$no_inv]->recordPembayaran->sum('nilai_pembayaran') ?? 0;
                $withdraw = $groupedInvSpesial[$no_inv]->recordWithdraw->sum('nilai_pembayaran') ?? 0;
                $nominal = $pembayaran + $withdraw;
                $isLunas = $nilai_invoice > 0 ? ($nominal >= $nilai_invoice) : false;
                $status = $isLunas ? ' (Lunas)' : '';

                if(isset($groupedInvSpesial[$no_inv]) && isset($groupedInvSpesial[$no_inv]->recordPembayaran) && $groupedInvSpesial[$no_inv]->recordPembayaran->isNotEmpty()) {
                    $tanggalPembayaran = $groupedInvSpesial[$no_inv]->recordPembayaran->first()->tgl_pembayaran;
                } else {
                    $tanggalPembayaran = null;
                }
                $po = $inv->no_po ?? null;

                return [
                    'no_invoice'            => $no_inv ? $no_inv . $status : null,
                    'nilai_invoice'         => $nilai_invoice,
                    'nilai_pembayaran'      => $nominal,
                    'nilai_pengurangan'     => $withdraw,
                    'revenue_invoice'       => $pembayaran,
                    'tanggal_pembayaran'    => $tanggalPembayaran,
                    'no_po'                 => $po,
                    'no_order'              => $items->first()['no_order'],
                    'no_quotation'          => $no_quotation,
                    'pelanggan_ID'          => $items->first()['pelanggan_ID'],
                    'nama_perusahaan'       => $items->first()['nama_perusahaan'],
                    'konsultan'             => $items->first()['konsultan'],
                    'periode'               => $items->min('periode'),
                    'kontrak'               => $items->first()['kontrak'],
                    'sales_id'              => $items->first()['sales_id'],
                    'sales_nama'            => $items->first()['sales_nama'],
                    'status_sampling'       => $items->pluck('status_sampling')->unique()->implode(', '),
                    'total_discount'        => $items->first()['total_discount'],
                    'total_ppn'             => $items->first()['total_ppn'],
                    'total_pph'             => $items->first()['total_pph'],
                    'biaya_akhir'           => $items->first()['biaya_akhir'],
                    'grand_total'           => $items->first()['grand_total'],
                    'total_revenue'         => $items->first()['total_revenue'],
                    'total_cfr'             => $items->first()['total_cfr'],
                    'tanggal_sampling_min'  => $items->min('tanggal_sampling_min'),
                    'is_lunas'              => $isLunas,
                    'created_at'            => $items->first()['created_at'],
                ];
            })->values();

        $withoutInvoice = $withoutInvoice->filter(function ($item) use ($noQTSpesial) {
            return !in_array($item['no_quotation'], $noQTSpesial);
        })->values();

        $firstGrouped = $groupedSpesial->merge($withoutInvoice)->values();

        $grouped = $withInvoice->groupBy('no_invoice')->map(function ($items) {
            return [
                'no_invoice'            => $items->first()['no_invoice'],
                'nilai_invoice'         => $items->first()['nilai_invoice'],
                'nilai_pembayaran'      => $items->first()['nilai_pembayaran'],
                'nilai_pengurangan'     => $items->first()['nilai_pengurangan'],
                'revenue_invoice'       => $items->first()['revenue_invoice'],
                'tanggal_pembayaran'    => $items->first()['tanggal_pembayaran'],
                'no_po'                 => $items->first()['no_po'],
                'no_order'              => $items->min('no_order'),
                'no_quotation'          => $items->first()['no_quotation'],
                'pelanggan_ID'          => $items->first()['pelanggan_ID'],
                'nama_perusahaan'       => $items->first()['nama_perusahaan'],
                'konsultan'             => $items->first()['konsultan'],
                'periode'               => $items->min('periode'),
                'kontrak'               => $items->first()['kontrak'],
                'sales_id'              => $items->first()['sales_id'],
                'sales_nama'            => $items->first()['sales_nama'],
                'status_sampling'       => $items->pluck('status_sampling')->unique()->implode(', '),
                'total_discount'        => $items->sum('total_discount'),
                'total_ppn'             => $items->sum('total_ppn'),
                'total_pph'             => $items->sum('total_pph'),
                'biaya_akhir'           => $items->sum('biaya_akhir'),
                'grand_total'           => $items->sum('grand_total'),
                'total_revenue'         => $items->sum('total_revenue'),
                'total_cfr'             => $items->sum('total_cfr'),
                'tanggal_sampling_min'  => $items->min('tanggal_sampling_min'),
                'is_lunas'              => $items->contains('is_lunas', false) ? false : true,
                'created_at'            => $items->first()['created_at'],
            ];
        })->values();
        $result = $grouped->merge($firstGrouped)->values();
        printf("[SchaduleUpdateQsd] [%s] Processing groupings completed\n", Carbon::now()->format('Y-m-d H:i:s'));
        return [$withInvoice, $groupedSpesial, $withoutInvoice, $firstGrouped, $grouped, $result];
    }

    /**
     * =====================================================
     * HELPER INVOICE
     * =====================================================
     */
    private static function buildInvoiceInfo($invoices)
    {
        if ($invoices->isEmpty()) {
            return [null, false, null, null, null, null, null, null];
        }

        $noInvoice = [];
        $isLunas   = false;
        $nilaiInvoice = 0;
        $nilaiPelunasan = 0;
        $pengurangan = 0;
        $revenueInvoice = 0;
        $tanggalPembayaran = [];
        $po = [];

        foreach ($invoices as $inv) {

            $nominal = ($inv->recordPembayaran ? $inv->recordPembayaran->sum('nilai_pembayaran') : 0)
                + ($inv->recordWithdraw ? $inv->recordWithdraw->sum('nilai_pembayaran') : 0);

            $status = $nominal >= $inv->nilai_tagihan ? ' (Lunas)' : '';


            if ($status == ' (Lunas)') {
                $isLunas = true;
            }

            $noInvoice[] = $inv->no_invoice . $status;

            $nilaiPelunasan += $nominal;
            $nilaiInvoice += $inv->nilai_tagihan;
            $revenueInvoice += ($inv->recordPembayaran ? $inv->recordPembayaran->sum('nilai_pembayaran') : 0);
            $pengurangan += ($inv->recordWithdraw ? $inv->recordWithdraw->sum('nilai_pembayaran') : 0);

            if(isset($inv->recordPembayaran) && $inv->recordPembayaran->isNotEmpty()) {
                $tanggalPembayaran[] = $inv->recordPembayaran->first()->tgl_pembayaran;
            }

            $po[] = $inv->no_po;
        }

        $po = array_unique($po);
        if ($nilaiPelunasan == 0) $nilaiPelunasan = null;
        return [implode(', ', $noInvoice), $isLunas, $nilaiPelunasan, $nilaiInvoice, $revenueInvoice, $pengurangan, implode(', ', $tanggalPembayaran) , implode(', ', $po)];
    }
}
