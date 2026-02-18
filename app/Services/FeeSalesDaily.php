<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

use Illuminate\Support\Facades\DB;

use App\Models\{
    MasterTargetSales,
    DailyQsd,
    ClaimFeeExternal,
    SummaryFeeSales,
};

class FeeSalesDaily
{
    private const INDO_MONTHS = [
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

    private function log($message, $type = 'INFO')
    {
        $colors = [
            'ERROR'   => "\033[31m", // merah
            'SUCCESS' => "\033[32m", // ijo
            'WARNING' => "\033[33m", // kuning
            'INFO'    => "\033[36m", // cyan
        ];

        $color = $colors[$type] ?? "\033[36m";
        $reset = "\033[0m";
        $time  = Carbon::now()->format('H:i:s');

        printf("[%s] %s[%-7s]%s %s\n", $time, $color, $type, $reset, $message);
    }

    public function run()
    {
        printf("\n\033[1;33m========================================================================\033[0m\n");
        printf("\033[1;33m  SUMMARY FEE SALES \033[0m\n");
        printf("\033[1;33m========================================================================\033[0m\n");

        DB::beginTransaction();
        try {
            $categoryStr = config('kategori.id');

            $start = Carbon::create(2026)->startOfYear();
            $end = Carbon::now()->endOfMonth();
            $periodRange = CarbonPeriod::create($start, '1 month', $end);

            foreach ($periodRange as $index => $period) {
                $year = $period->year;
                $month = $period->format('m');
                $period = $period->format('Y-m');
                $monthStr = self::INDO_MONTHS[$month];

                printf("\n[%d/%d] Tracking Period: %s\n", $index + 1, $periodRange->count(), $period);

                $masterTargetSales = MasterTargetSales::with('sales:id,nama_lengkap')->where(['tahun' => $year, 'is_active' => true])->whereNotNull($monthStr)->get();
                $this->log("Found " . $masterTargetSales->count() . " sales targets active for this period.");
                foreach ($masterTargetSales as $index2 => $targetSales) {
                    $salesId = $targetSales->karyawan_id;
                    $salesName = $targetSales->sales->nama_lengkap;

                    printf("\n--- [%d/%d] Processing Sales: %s ---\n", $index2 + 1, $masterTargetSales->count(), $salesName);

                    $quotations = DailyQsd::with('orderHeader.orderDetail')
                        ->where('sales_id', $salesId)
                        ->whereYear('tanggal_kelompok', $year)
                        ->whereMonth('tanggal_kelompok', $month)
                        ->get();

                    $quotations = $quotations->map(function ($qsd) {
                        $totalFeeExternal = ClaimFeeExternal::where(['no_order' => $qsd->no_order, 'is_active' => true])->when($qsd->periode, fn($q) => $q->where('periode', $qsd->periode))->sum('nominal');

                        $totalRevenue = $qsd->total_revenue - ($totalFeeExternal + $qsd->nilai_pengurangan);

                        $qsd->total_revenue = $totalRevenue;
                        $qsd->total_revenue_yg_lunas = $qsd->is_lunas ? $totalRevenue : 0;

                        if ($qsd->periode) {
                            $orderDetail = optional($qsd->orderHeader)->orderDetail ? $qsd->orderHeader->orderDetail->filter(fn($od) => $od->periode === $qsd->periode)->values() : collect();
                            if ($orderDetail->isNotEmpty()) {
                                $qsd->orderHeader->setRelation('orderDetail', $orderDetail);
                            }
                        }

                        return $qsd;
                    })->filter()->values();

                    if ($quotations->isEmpty()) {
                        $this->log("No eligible quotations found for calculation. Skipping.", "WARNING");
                        continue;
                    }

                    $this->log("Checking ...");

                    // CALCULATE CATEGORY
                    $targetCategory = collect($targetSales->{$monthStr});
                    $achievedCategoryDetails = $targetCategory->map(
                        function ($_, $category) use ($quotations, $targetCategory, $categoryStr) {
                            $target = $targetCategory[$category];
                            $achieved = $quotations->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)->filter(fn($orderDetail) => collect($categoryStr[$category])->contains($orderDetail->kategori_3))->count();

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
                    $percentageCategory = $totalAchievedPoint / $totalTargetPoint;

                    // FEE AMOUNT
                    $targetAmount = json_decode($targetSales->target, true)[$period];
                    $achievedAmount = $quotations->sum('total_revenue');
                    $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                    $percentageAmount = $achievedAmount / $targetAmount;
                    $basis = $rate * $percentageCategory;
                    $paidAchievedAmount = $quotations->sum('total_revenue_yg_lunas');

                    // TOTAL FEE
                    $estimatedFee = $achievedAmount * $basis;
                    $claimedFee = $paidAchievedAmount * $basis;

                    // SUMMARY FEE SALES
                    $summaryFeeSales = SummaryFeeSales::firstOrNew([
                        'sales_id' => $salesId,
                        'tahun'    => $year
                    ]);

                    $oldSummary = $summaryFeeSales->{$monthStr};

                    $summary = [
                        'category' => [
                            'total_target'              => $achievedCategoryDetails->sum('target'),
                            'total_achieved'            => $achievedCategoryDetails->sum('achieved'),
                            'total_point'               => $totalAchievedPoint . '/' . $totalTargetPoint,
                            'achieved_category_details' => $achievedCategoryDetails->toArray(),
                        ],
                        'amount' => [
                            'target'               => $targetAmount,
                            'achieved'             => $achievedAmount,
                            'rate'                 => $rate,
                            'percentage'           => $percentageAmount,
                            'basis'                => $basis,
                            'paid_achieved_amount' => $paidAchievedAmount,
                            'estimated_fee'        => $estimatedFee,
                            'claimed_fee'          => $claimedFee,
                        ],
                    ];

                    if ($oldSummary != $summary) {
                        $summaryFeeSales->{$monthStr} = $summary;
                        $summaryFeeSales->save();

                        $this->log("Summary updated successfully", "SUCCESS");
                    } else {
                        $this->log("No changes. Skipping.", "WARNING");
                    }
                }
            }
            DB::commit();

            printf("\n");
            $this->log("Transaction Committed successfully", "SUCCESS");
        } catch (\Throwable $th) {
            DB::rollBack();

            printf("\n");
            $this->log("Error: " . $th->getMessage() . " in Line " . $th->getLine(), "ERROR");
            $this->log("Transaction Rolled Back", "ERROR");
        }
    }
}
