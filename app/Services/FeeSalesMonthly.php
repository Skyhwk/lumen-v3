<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\{
    MasterKaryawan,
    MasterTargetSales,
    MasterFeeSales,
    QuotationKontrakH,
    QuotationNonKontrak,
    MutasiFeeSales,
    SaldoFeeSales,
};

class FeeSalesMonthly
{
    private $currentYear;
    private $currentMonth;

    public function __construct()
    {
        $this->currentYear = Carbon::now()->year;
        $this->currentMonth = 10;
    }

    private $monthStr = [
        '01' => 'januari',
        '02' => 'februari',
        '03' => 'maret',
        '04' => 'april',
        '05' => 'mei',
        '06' => 'juni',
        '07' => 'juli',
        '08' => 'agustus',
        '09' => 'september',
        '10' => 'oktober',
        '11' => 'november',
        '12' => 'desember',
    ];

    private $categoryStr = [
        'AIR LIMBAH' => [
            '2-Air Limbah Domestik',
            '3-Air Limbah Industri',
            '51-Air Limbah',
        ],
        'AIR BERSIH' => [
            '1-Air Bersih',
        ],
        'AIR MINUM' => [
            '4-Air Minum',
        ],
        'AIR SUNGAI' => [
            '54-Air Sungai',
        ],
        'AIR LAUT' => [
            '5-Air Laut',
        ],
        'AIR LAINNYA' => [
            '72-Air Tanah',
            '63-Air Higiene Sanitasi',
            '64-Air Khusus',
            '40-Air Kolam Renang',
            '56-Air Danau',
            '6-Air Permukaan',
            '117-Air Reverse Osmosis',
            '62-Air Higiene Sanitasi',
            '112-Air Lindi',
        ],
        'UDARA AMBIENT' => [
            '11-Udara Ambient',
        ],
        'UDARA LINGKUNGAN KERJA' => [
            '27-Udara Lingkungan Kerja',
        ],
        'KEBISINGAN' => [
            '23-Kebisingan',
            '25-Kebisingan (Indoor)',
            '24-Kebisingan (24 Jam)',
        ],
        'PENCAHAYAAN' => [
            '28-Pencahayaan',
        ],
        'GETARAN' => [
            '13-Getaran',
            '19-Getaran (Mesin)',
            '20-Getaran (Seluruh Tubuh)',
            '17-Getaran (Lengan & Tangan)',
            '15-Getaran (Kejut Bangunan)',
            '14-Getaran (Bangunan)',
            '18-Getaran (Lingkungan)',
        ],
        'IKLIM KERJA' => [
            '21-Iklim Kerja',
        ],
        'UDARA LAINNYA' => [
            '53-Ergonomi',
            '12-Udara Angka Kuman',
            '22-Kebauan',
            '46-Udara Swab Test',
            '29-Udara Umum',
            '118-Psikologi',
            '26-Kualitas Udara Dalam Ruang',
        ],
        'EMISI SUMBER BERGERAK' => [
            '30-Emisi Kendaraan',
            '32-Emisi Kendaraan (Solar)',
            '31-Emisi Kendaraan (Bensin)',
            '116-Emisi Kendaraan (Gas)',
        ],
        'EMISI SUMBER TIDAK BERGERAK' => [
            '34-Emisi Sumber Tidak Bergerak',
        ],
        'EMISI ISOKINETIK' => [
            '119-Emisi Isokinetik',
        ],
    ];

    public function run()
    {
        DB::beginTransaction();
        try {
            $month = $this->monthStr[$this->currentMonth];

            $salesList = MasterKaryawan::whereIn('id_jabatan', [
                15, // Sales Manager
                21, // Sales Supervisor
                22, // Sales Admin Supervisor
                23, // Senior Sales Admin Staff
                24, // Sales Officer
                25, // Sales Admin Staff
                140, // Sales Assistant Manager
                145, // Sales Intern
                147, // Sales & Marketing Manager
                154, // Senior Sales Manager
                155, // Sales Executive
                156, // Sales Staff
                148, // Customer Relation Officer
                157, // Customer Relationship Officer Manager
            ])
                ->where('is_active', true)
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            foreach ($salesList as $sales) {
                $targetSales = MasterTargetSales::where([
                    'karyawan_id' => $sales->id,
                    'tahun' => $this->currentYear,
                    'is_active' => true
                ])->whereNotNull($month)->latest()->first();

                if (!$targetSales) continue;

                $masterFeeSalesExists = MasterFeeSales::where([
                    'sales_id' => $sales->id,
                    'periode' => $this->currentYear . "-" . $this->currentMonth
                ])->exists();

                if ($masterFeeSalesExists) continue;

                $quotations = collect([]);

                $models = [QuotationKontrakH::class, QuotationNonKontrak::class];
                foreach ($models as $model) {
                    $quotations = $quotations->merge(
                        $model::with([
                            'orderHeader.orderDetail' => fn($q) => $q->where('status', 3),
                            'orderHeader.invoices.recordWithdraw'
                        ])->where([
                            'sales_id' => $sales->id,
                            'is_active' => true,
                        ])
                            ->whereHas('orderHeader', fn($q) => $q->whereMonth('tanggal_order', $this->currentMonth)->whereYear('tanggal_order', $this->currentYear))
                            ->whereHas('orderHeader.orderDetail', fn($q) => $q->where('status', 3))
                            ->whereHas('orderHeader.invoices')
                            ->get()
                            ->filter(function ($item) {
                                $invoices = $item->orderHeader->invoices;

                                $totalTagihan = $invoices->sum('nilai_tagihan');
                                $totalPelunasan = $invoices->sum('nilai_pelunasan');

                                $totalWithdraw = $invoices->flatMap->recordWithdraw->sum('nilai_pembayaran');

                                $isLunas = $totalTagihan == $totalPelunasan + $totalWithdraw;

                                return $isLunas;
                            })
                    );
                }

                if ($quotations->isEmpty()) continue;

                // ACHIEVED AMOUNT
                $totalBiayaAkhir = $quotations->sum('biaya_akhir');
                $totalPph = $quotations->sum('total_pph');
                $totalPpn = $quotations->sum('total_ppn');
                $achievedAmount = $totalBiayaAkhir + $totalPph - $totalPpn;

                // ACHIEVED CATEGORY
                $achievedCategory = collect($targetSales->$month)
                    ->map(function ($value, $category) use ($quotations) {
                        $categoryTarget = collect($this->categoryStr[$category]);

                        return $quotations
                            ->flatMap(fn($q) => $q->orderHeader->orderDetail)
                            ->filter(fn($orderDetail) => $categoryTarget->contains($orderDetail->kategori_3))
                            ->count();
                    })
                    ->toArray();

                // RECAP
                $recap = $quotations->map(fn($row) => [
                    'no_document' => $row->no_document,
                    'tanggal_penawaran' => $row->tanggal_penawaran,
                    'order_header' => [
                        'no_order' => $row->orderHeader->no_order,
                        'tanggal_order' => $row->orderHeader->tanggal_order,
                        'order_detail' => $row->orderHeader->orderDetail->map(fn($d) => [
                            'kategori_3' => $d->kategori_3,
                            'cfr' => $d->cfr
                        ])->values(),
                        'invoices' => $row->orderHeader->invoices->map(fn($inv) => [
                            'no_invoice' => $inv->no_invoice,
                            'nilai_pelunasan' => $inv->nilai_pelunasan,
                            'record_withdraw' => $inv->recordWithdraw->map(fn($w) => [
                                'nilai_pembayaran' => $w->nilai_pembayaran
                            ])->values()
                        ])->values(),
                    ],
                    'biaya_akhir' => $row->biaya_akhir,
                ]);

                // MASTER FEE SALES
                $masterFeeSales = new MasterFeeSales();

                $masterFeeSales->sales_id = $sales->id;
                $masterFeeSales->periode = $this->currentYear . "-" . $this->currentMonth;
                $masterFeeSales->target = json_encode($targetSales->$month);
                $masterFeeSales->achieved = json_encode([
                    'amount' => $achievedAmount,
                    'category' => $achievedCategory,
                    'recap' => $recap
                ]);
                $masterFeeSales->created_by = 'System';
                $masterFeeSales->updated_by = 'System';

                $masterFeeSales->save();

                // MUTASI FEE SALES
                Carbon::setLocale('id');

                $mutasiFeeSales = new MutasiFeeSales();

                $mutasiFeeSales->sales_id = $sales->id;
                $mutasiFeeSales->batch_number = Carbon::now()->format('YmdHis');
                $mutasiFeeSales->mutation_type = 'Debit';
                $mutasiFeeSales->amount = $achievedAmount;
                $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->currentYear . '-' . $this->currentMonth)->translatedFormat('F Y');
                $mutasiFeeSales->status = 'Processing';
                $mutasiFeeSales->created_by = 'System';
                $mutasiFeeSales->updated_by = 'System';

                $mutasiFeeSales->save();

                // SALDO FEE SALES
                $saldoFeeSales = SaldoFeeSales::firstOrNew(['sales_id' => $sales->id]);
                $saldoFeeSales->activeBalance = ($saldoFeeSales->activeBalance ?: 0) + $achievedAmount;
                $saldoFeeSales->updated_by = 'System';
                $saldoFeeSales->save();
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[FeeSalesMonthly] Error: ' . $th->getMessage());
        }
    }
}
