<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\{
    MasterKaryawan,
    DailyQsd,
    MasterTargetSales,
    MasterFeeSales,
    MutasiFeeSales,
    SaldoFeeSales,
};

class FeeSalesMonthly
{
    private $currentYear;
    private $currentMonth;
    private $currentPeriod;
    private $currentMonthStr;

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

    public function __construct()
    {
        $this->currentYear = Carbon::now()->year;
        $this->currentMonth = Carbon::now()->format('m');
        $this->currentPeriod = $this->currentYear . "-" . $this->currentMonth;

        $monthStr = [
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

        $this->currentMonthStr = $monthStr[$this->currentMonth];
    }

    public function run()
    {
        DB::beginTransaction();
        try {
            $salesList = MasterKaryawan::whereIn('id_jabatan', [
                24, // Sales Officer
                148, // Customer Relation Officer
            ])
                ->orWhere('nama_lengkap', 'Novva Novita Ayu Putri Rukmana')
                ->where('is_active', true)
                ->orderBy('nama_lengkap', 'asc')
                ->get();

            foreach ($salesList as $sales) {
                $masterTargetSales = MasterTargetSales::where(['karyawan_id' => $sales->id, 'tahun' => $this->currentYear, 'is_active' => true])->whereNotNull($this->currentMonthStr)->latest()->first();
                if (!$masterTargetSales) continue;

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $sales->id, 'period' => $this->currentPeriod])->exists();
                if ($masterFeeSalesExists) continue;

                $feeSalesRecap = MasterFeeSales::where('sales_id', $sales->id)->get()->flatMap(fn($mfs) => collect(json_decode($mfs->recap, true)));

                $quotations = DailyQsd::with(['orderHeader.orderDetail', 'orderHeader.invoices.recordWithdraw'])
                    ->where('sales_id', $sales->id)
                    ->whereDate('tanggal_sampling_min', '>=', '2025-10-01')
                    ->whereDate('tanggal_sampling_min', '<=', Carbon::create($this->currentYear, $this->currentMonth)->endOfMonth())
                    ->where('is_lunas', true)
                    ->get()
                    ->map(function ($qsd) use ($feeSalesRecap) {
                        $existsInFeeSales = $feeSalesRecap->contains(function ($recap) use ($qsd) {
                            if ($recap['no_order'] !== $qsd->no_order) return false;
                            if (!$qsd->periode) return true;

                            return $recap['periode'] === $qsd->periode;
                        });

                        if ($existsInFeeSales) return null;

                        if ($qsd->periode) {
                            $qsd->orderHeader->orderDetail = $qsd->orderHeader->orderDetail->filter(fn($od) => $od->periode === $qsd->periode)->values();
                        }

                        return $qsd;
                    })
                    ->filter()
                    ->values();

                if ($quotations->isEmpty()) continue;

                // FEE AMOUNT
                $targetAmount = json_decode($masterTargetSales->target, true)[$this->currentPeriod];
                $achievedAmount = $quotations->sum('total_revenue');
                $percentageAmount = $achievedAmount / $targetAmount;
                $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                // $feeAmount = $achievedAmount * $rate;

                // FEE CATEGORY
                $targetCategory = collect($masterTargetSales->{$this->currentMonthStr});
                $achievedCategoryDetails = $targetCategory->map(
                    function ($_, $category) use ($quotations, $targetCategory) {
                        $target = $targetCategory[$category];

                        $achieved = $quotations->flatMap(fn($q) => $q->orderHeader->orderDetail)
                            ->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))
                            ->count();

                        return [
                            'target' => $target,
                            'achieved' => $achieved,
                            'point' => $target && $achieved ? floor($achieved / $target) : 0,
                        ];
                    }
                );

                $totalAchievedPoint = $achievedCategoryDetails->sum('point');
                $totalAchievedPoint = $totalAchievedPoint == 0 ? 1 : $totalAchievedPoint;
                $totalTargetPoint = $targetCategory->filter(fn($value) => $value > 0)->count();

                $achievedCategory = collect([
                    'total_target' => $achievedCategoryDetails->sum('target'),
                    'total_achieved' => $achievedCategoryDetails->sum('achieved'),
                    'total_point' => $totalAchievedPoint . '/' . $totalTargetPoint,
                    'achieved_category_details' => $achievedCategoryDetails->toArray(),
                ]);
                $percentageCategory = $achievedCategoryDetails->sum('point') / $totalTargetPoint;

                // TOTAL FEE
                $totalFee = $totalAchievedPoint / $totalTargetPoint * $rate * $achievedAmount;

                // RECAP
                $recap = $quotations->map(fn($quotation) => [
                    'no_document' => $quotation->no_quotation,
                    'no_order' => $quotation->no_order,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'periode' => $quotation->periode,
                    'kategori_3' => $quotation->orderHeader->orderDetail->map(fn($orderDetail) => $orderDetail->kategori_3),
                    'no_invoice' => $quotation->no_invoice,
                    'total_revenue' => $quotation->total_revenue,
                ])->values();

                // MASTER FEE SALES
                $masterFeeSales = new MasterFeeSales();

                $masterFeeSales->sales_id = $sales->id;
                $masterFeeSales->period = $this->currentPeriod;
                $masterFeeSales->target_amount = $targetAmount;
                $masterFeeSales->achieved_amount = $achievedAmount;
                $masterFeeSales->percentage_amount = $percentageAmount;
                $masterFeeSales->rate = $rate;

                $masterFeeSales->target_category = json_encode($targetCategory);
                $masterFeeSales->achieved_category = json_encode($achievedCategory);
                $masterFeeSales->percentage_category = $percentageCategory;

                $masterFeeSales->total_fee = $totalFee;

                $masterFeeSales->recap = json_encode($recap);

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
                $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->currentPeriod)->translatedFormat('F Y');
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
