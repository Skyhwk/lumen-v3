<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

use App\Models\{
    MasterTargetSales,
    DailyQsd,
    MasterFeeSales,
    ClaimFeeExternal,
    RekapFeeSales,
    MutasiFeeSales,
    SaldoFeeSales,
};

class FeeSalesMonthly
{
    private $cutOff;

    private $year;
    private $month;
    private $period;

    private $timestamp;

    private $monthStr;
    private $categoryStr;

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

    public function __construct()
    {
        Carbon::setLocale('id');

        $this->cutOff = Carbon::create(2026, 1, 1);

        $now = Carbon::now();

        $this->year = $now->year;
        $this->month = $now->format('m');
        $this->period = Carbon::create($this->year, $this->month)->format('Y-m');

        $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();

        $this->monthStr = self::INDO_MONTHS[$this->month];
        $this->categoryStr = config('kategori.id');
    }

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

    public function run($period = null)
    {
        if ($period) {
            [$year, $month] = explode('-', $period);

            $this->year = $year;
            $this->month = $month;
            $this->period = $period;

            $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();

            $this->monthStr = self::INDO_MONTHS[$this->month];
        }

        printf("\n\033[1;33m========================================================================\033[0m\n");
        printf("\033[1;33m  FEE SALES (PHASE 1) - PERIOD: %s \033[0m\n", $this->period);
        printf("\033[1;33m========================================================================\033[0m\n");

        DB::beginTransaction();
        try {
            $masterTargetSales = MasterTargetSales::with('sales:id,nama_lengkap')->where(['tahun' => $this->year, 'is_active' => true])->whereNotNull($this->monthStr)->get();
            $this->log("Found " . $masterTargetSales->count() . " sales targets active for this period.");
            foreach ($masterTargetSales as $index => $targetSales) {
                $salesId = $targetSales->karyawan_id;
                $salesName = $targetSales->sales->nama_lengkap;
                printf("\n--- [%d/%d] Processing Sales: %s ---\n", $index + 1, $masterTargetSales->count(), $salesName);

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $this->period, 'is_active' => true])->exists();
                if ($masterFeeSalesExists) {
                    $this->log("Fee Sales ALREADY EXISTS. Skipping.", "WARNING");
                    continue;
                }

                $existingRecaps = RekapFeeSales::whereHas('masterFeeSales', fn($q) => $q->where(['sales_id' => $salesId, 'is_active' => true]))->get();

                $isExistsInFeeSales = fn($qsd) => $existingRecaps->contains(function ($recap) use ($qsd) {
                    if ($recap->no_order !== $qsd->no_order) return false;
                    if (!$qsd->periode) return true;

                    return $recap->periode === $qsd->periode;
                });

                $quotations = DailyQsd::with('orderHeader.orderDetail')
                    ->where('sales_id', $salesId)
                    ->whereDate('tanggal_kelompok', '>=', $this->cutOff)
                    ->whereDate('tanggal_kelompok', '<=', $this->timestamp)
                    ->get();

                $initialCount = $quotations->count();
                $this->log("Fetched $initialCount QSD records");

                $quotations = $quotations->map(function ($qsd) use ($isExistsInFeeSales) {
                    if ($isExistsInFeeSales($qsd)) return null;

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

                $this->log("Calculating ...");

                // CALCULATE CATEGORY
                $targetCategory = collect($targetSales->{$this->monthStr});
                $achievedCategoryDetails = $targetCategory->map(
                    function ($_, $category) use ($quotations, $targetCategory) {
                        $target = $targetCategory[$category];
                        $achieved = $quotations->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)->filter(fn($orderDetail) => collect($this->categoryStr[$category])->contains($orderDetail->kategori_3))->count();

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
                $percentageCategory = $totalAchievedPoint / $totalTargetPoint;

                // FEE AMOUNT
                $targetAmount = json_decode($targetSales->target, true)[$this->period];
                $achievedAmount = $quotations->sum('total_revenue');
                $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                $percentageAmount = $achievedAmount / $targetAmount;
                $basis = $rate * $percentageCategory;
                $paidAchievedAmount = $quotations->sum('total_revenue_yg_lunas');

                // TOTAL FEE
                $estimatedFee = $achievedAmount * $basis;
                $claimedFee = $paidAchievedAmount * $basis;

                // MASTER FEE SALES
                $this->log("Saving to MasterFeeSales ...");
                $masterFeeSales = new MasterFeeSales();

                $masterFeeSales->sales_id = $salesId;
                $masterFeeSales->period = $this->period;
                $masterFeeSales->target_category = json_encode($targetCategory);
                $masterFeeSales->achieved_category = json_encode($achievedCategory);
                $masterFeeSales->percentage_category = $percentageCategory;
                $masterFeeSales->target_amount = $targetAmount;
                $masterFeeSales->achieved_amount = $achievedAmount;
                $masterFeeSales->rate = $rate;
                $masterFeeSales->percentage_amount = $percentageAmount;
                $masterFeeSales->basis = $basis;
                $masterFeeSales->paid_achieved_amount = $paidAchievedAmount;
                $masterFeeSales->estimated_fee = $estimatedFee;
                $masterFeeSales->claimed_fee = $claimedFee;
                $masterFeeSales->created_by = 'System';
                $masterFeeSales->created_at = $this->timestamp;

                $masterFeeSales->save();

                // REKAP FEE SALES
                $this->log("Saving Recaps ...");
                RekapFeeSales::insert($quotations->map(fn($quotation) => [
                    'fee_sales_id' => $masterFeeSales->id,
                    'no_document' => $quotation->no_quotation,
                    'no_order' => $quotation->no_order,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'periode' => $quotation->periode,
                    'kategori_3' => json_encode(optional($quotation->orderHeader)->orderDetail ? $quotation->orderHeader->orderDetail->pluck('kategori_3')->toArray() : []),
                    'no_invoice' => $quotation->no_invoice,
                    'is_lunas' => $quotation->is_lunas,
                    'total_revenue' => $quotation->total_revenue,
                ])->values()->toArray());

                // MUTASI FEE SALES
                if ($claimedFee > 0) {
                    $this->log("Creating Mutation Record ...", "INFO");
                    $mutasiFeeSales = new MutasiFeeSales();

                    $mutasiFeeSales->sales_id = $salesId;
                    $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                    $mutasiFeeSales->period = $this->period;
                    $mutasiFeeSales->mutation_type = 'Kredit';
                    $mutasiFeeSales->amount = $claimedFee;
                    $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->period)->translatedFormat('F Y');
                    $mutasiFeeSales->status = 'Done';
                    $mutasiFeeSales->created_by = 'System';
                    $mutasiFeeSales->created_at = $this->timestamp;

                    $mutasiFeeSales->save();
                }

                // SALDO FEE SALES
                $this->log("Updating Balances ...", "INFO");
                $saldoFeeSales = SaldoFeeSales::where(['sales_id' => $salesId, 'is_active' => true])->latest()->first();
                if ($saldoFeeSales) {
                    $saldoFeeSales->active_balance += $claimedFee;
                    $saldoFeeSales->updated_by = 'System';
                    $saldoFeeSales->updated_at = $this->timestamp;
                } else {
                    $saldoFeeSales = new SaldoFeeSales();
                    $saldoFeeSales->sales_id = $salesId;
                    $saldoFeeSales->active_balance = $claimedFee;
                    $saldoFeeSales->created_by = 'System';
                    $saldoFeeSales->created_at = $this->timestamp;
                }
                $saldoFeeSales->save();

                $this->log("Fee Sales saved successfully", "SUCCESS");
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
