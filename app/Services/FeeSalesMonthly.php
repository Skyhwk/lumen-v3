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
        $this->currentMonth = Carbon::now()->format('m');
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
                $masterTargetSales = MasterTargetSales::where(['karyawan_id' => $sales->id, 'tahun' => $this->currentYear, 'is_active' => true])->whereNotNull($month)->latest()->first();
                if (!$masterTargetSales) continue;

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $sales->id, 'periode' => $this->currentYear . "-" . $this->currentMonth])->exists();
                if ($masterFeeSalesExists) continue;

                $orderDetailFilter = fn($q) => $q->where('is_approve', true)
                    ->whereYear('tanggal_sampling', $this->currentYear)
                    ->whereMonth('tanggal_sampling', $this->currentMonth)
                    ->where('tanggal_sampling', '>=', '2025-12-17');

                $quotations = collect([QuotationKontrakH::class, QuotationNonKontrak::class])
                    ->flatMap(fn($model) => $model::with(['orderHeader.orderDetail' => $orderDetailFilter, 'orderHeader.invoices.recordWithdraw'])
                        ->where(['sales_id'  => $sales->id, 'is_active' => true])
                        ->whereHas('orderHeader.orderDetail', $orderDetailFilter)
                        ->whereHas('orderHeader.invoices')
                        ->get()
                        ->filter(fn($quotation) => ($invoices = $quotation->orderHeader->invoices) && $invoices->sum('nilai_tagihan') === ($invoices->sum('nilai_pelunasan') + $invoices->flatMap->recordWithdraw->sum('nilai_pembayaran'))));

                if ($quotations->isEmpty()) continue;

                // ACHIEVED CATEGORY
                $achievedCategory = collect($masterTargetSales->$month)->map(fn($_, $category) => $quotations->flatMap(fn($q) => $q->orderHeader->orderDetail)->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))->count());

                // ACHIEVED AMOUNT
                $achievedAmount = $quotations->sum('biaya_akhir') + $quotations->sum('total_pph') - $quotations->sum('total_ppn');

                // RECAP
                $recap = $quotations->map(fn($quotation) => [
                    'no_document' => $quotation->no_document,
                    'tanggal_penawaran' => $quotation->tanggal_penawaran,
                    'order_header' => [
                        'no_order' => $quotation->orderHeader->no_order,
                        'order_detail' => $quotation->orderHeader->orderDetail
                            ->map(fn($orderDetail) => [
                                'kategori_3' => $orderDetail->kategori_3,
                                'cfr' => $orderDetail->cfr,
                                'tanggal_sampling' => $orderDetail->tanggal_sampling,
                                'approved_at' => $orderDetail->approved_at
                            ]),
                        'invoices' => $quotation->orderHeader->invoices
                            ->map(fn($invoice) => [
                                'no_invoice' => $invoice->no_invoice,
                                'nilai_pelunasan' => $invoice->nilai_pelunasan,
                                'record_withdraw' => $invoice->recordWithdraw->map(fn($w) => ['nilai_pembayaran' => $w->nilai_pembayaran])
                            ]),
                    ],
                    'biaya_akhir' => $quotation->biaya_akhir,
                    'total_pph' => $quotation->total_pph,
                    'total_ppn' => $quotation->total_ppn,
                ]);

                // FEE CATEGORY & AMOUNT
                $targetAmount = json_decode($masterTargetSales->target, true)[$this->currentYear . "-" . $this->currentMonth];
                $percentage = $achievedAmount >= $targetAmount ? 0.05 : 0.01;
                $countAchievedCategory = $achievedCategory->sum();
                $countTargetCategory = collect($masterTargetSales->$month)->sum();
                $feeCategory = $countAchievedCategory >= $countTargetCategory ? $countAchievedCategory / $countTargetCategory * $percentage * $achievedAmount : 0;
                $feeAmount = $achievedAmount * $percentage;

                $totalFee = $feeCategory + $feeAmount;

                // MASTER FEE SALES
                $masterFeeSales = new MasterFeeSales();

                $masterFeeSales->sales_id = $sales->id;
                $masterFeeSales->periode = $this->currentYear . "-" . $this->currentMonth;
                $masterFeeSales->target_category = json_encode($masterTargetSales->$month);
                $masterFeeSales->target_amount = $targetAmount;
                $masterFeeSales->achieved_category = json_encode($achievedCategory);
                $masterFeeSales->achieved_amount = $achievedAmount;
                $masterFeeSales->recap = json_encode($recap);
                $masterFeeSales->fee_category = $feeCategory;
                $masterFeeSales->fee_amount = $feeAmount;
                $masterFeeSales->total_fee = $totalFee;
                $masterFeeSales->created_by = 'System';
                $masterFeeSales->updated_by = 'System';

                $masterFeeSales->save();

                // MUTASI FEE SALES
                Carbon::setLocale('id');

                $mutasiFeeSales = new MutasiFeeSales();

                $mutasiFeeSales->sales_id = $sales->id;
                $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                $mutasiFeeSales->mutation_type = 'Debit';
                $mutasiFeeSales->amount = $totalFee;
                $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->currentYear . '-' . $this->currentMonth)->translatedFormat('F Y');
                $mutasiFeeSales->status = 'Done';
                $mutasiFeeSales->created_by = 'System';
                $mutasiFeeSales->updated_by = 'System';

                $mutasiFeeSales->save();

                // SALDO FEE SALES
                $saldoFeeSales = SaldoFeeSales::firstOrNew(['sales_id' => $sales->id]);
                $saldoFeeSales->active_balance = ($saldoFeeSales->active_balance ?: 0) + $totalFee;
                $saldoFeeSales->created_by = 'System';
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
