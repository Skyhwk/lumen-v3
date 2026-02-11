<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\{
    ClaimFeeExternal,
    DailyQsd,
    MasterTargetSales,
    MasterFeeSales,
    DasarPerhitunganFeeSales,
    MutasiFeeSales,
    SaldoFeeSales,
};

class FeeSalesMonthly
{
    private $year;
    private $month;
    private $period;
    private $timestamp;

    private $monthStr;
    private $categoryStr;

    private const INDO_MONTH = [
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
        $now = Carbon::now();

        $this->year = $now->year;
        $this->month = $now->format('m');
        $this->period = $this->year . "-" . $this->month;
        $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();

        $this->monthStr = self::INDO_MONTH[$this->month];
        $this->categoryStr = config('kategori.id');
    }

    public function run()
    {
        DB::beginTransaction();
        try {
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly\n", Carbon::now());

            $masterTargetSales = MasterTargetSales::where('tahun', $this->year)->where('is_active', true)->whereNotNull($this->monthStr)->get();
            foreach ($masterTargetSales as $targetSales) {
                $salesId = $targetSales->karyawan_id;

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $this->period])->exists();
                if ($masterFeeSalesExists) continue;

                $feeSalesRecap = MasterFeeSales::where('sales_id', $salesId)->get()->flatMap(fn($mfs) => collect(json_decode($mfs->recap, true)));
                $isExistsInFeeSales = fn($qsd) => $feeSalesRecap->contains(function ($recap) use ($qsd) {
                    if ($recap['no_order'] !== $qsd->no_order) return false;
                    if (!$qsd->periode) return true;

                    return $recap['periode'] === $qsd->periode;
                });

                $quotations = DailyQsd::with('orderHeader.orderDetail')
                    ->where('sales_id', $salesId)
                    ->whereDate('tanggal_kelompok', '>=', '2026-01-01')
                    ->whereDate('tanggal_kelompok', '<=', $this->timestamp)
                    // ->where('is_lunas', true)
                    ->get()
                    ->map(function ($qsd) use ($isExistsInFeeSales) {
                        if ($isExistsInFeeSales($qsd)) return null;

                        $totalFeeExternal = ClaimFeeExternal::where('no_order', $qsd->no_order)->when($qsd->periode, fn($q) => $q->where('periode', $qsd->periode))->where('is_active', true)->sum('nominal');

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
                    })
                    ->filter()
                    ->values();

                if ($quotations->isEmpty()) continue;

                // CALCULATE CATEGORY
                $targetCategory = collect($targetSales->{$this->monthStr});
                $achievedCategoryDetails = $targetCategory->map(
                    function ($_, $category) use ($quotations, $targetCategory) {
                        $target = $targetCategory[$category];

                        $achieved = $quotations->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)
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
                $percentageCategory = $totalAchievedPoint / $totalTargetPoint;

                // FEE AMOUNT
                $targetAmount = json_decode($targetSales->target, true)[$this->period];
                $achievedAmount = $quotations->sum('total_revenue');
                $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                $percentageAmount = $achievedAmount / $targetAmount;
                $paidAchievedAmount = $quotations->sum('total_revenue_yg_lunas');
                $basis = $rate * $percentageCategory;

                // TOTAL FEE
                $totalFee = $paidAchievedAmount * $basis;

                // RECAP
                $recap = $quotations->map(fn($quotation) => [
                    'no_document' => $quotation->no_quotation,
                    'no_order' => $quotation->no_order,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'periode' => $quotation->periode,
                    'kategori_3' => optional($quotation->orderHeader)->orderDetail ? $quotation->orderHeader->orderDetail->pluck('kategori_3')->toArray() : [],
                    'no_invoice' => $quotation->no_invoice,
                    'total_revenue' => $quotation->total_revenue,
                ])->values();

                // MASTER FEE SALES
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
                $masterFeeSales->paid_achieved_amount = $paidAchievedAmount;
                $masterFeeSales->basis = $basis;

                $masterFeeSales->total_fee = $totalFee;

                $masterFeeSales->recap = json_encode($recap);

                $masterFeeSales->created_by = 'System';
                $masterFeeSales->created_at = $this->timestamp;

                $masterFeeSales->save();

                // DASAR PERHITUNGAN FEE SALES
                $dasarPerhitunganFeeSales = new DasarPerhitunganFeeSales();

                $dasarPerhitunganFeeSales->sales_id = $salesId;
                $dasarPerhitunganFeeSales->period = $this->period;
                $dasarPerhitunganFeeSales->target_category = json_encode($targetCategory);
                $dasarPerhitunganFeeSales->achieved_category = json_encode($achievedCategory);
                $dasarPerhitunganFeeSales->percentage_category = $percentageCategory;
                $dasarPerhitunganFeeSales->target_amount = $targetAmount;
                $dasarPerhitunganFeeSales->achieved_amount = $achievedAmount;
                $dasarPerhitunganFeeSales->rate = $rate;
                $dasarPerhitunganFeeSales->percentage_amount = $percentageAmount;
                $dasarPerhitunganFeeSales->paid_achieved_amount = $paidAchievedAmount;
                $dasarPerhitunganFeeSales->basis = $basis;

                $dasarPerhitunganFeeSales->total_fee = $totalFee;

                $dasarPerhitunganFeeSales->recap = json_encode($recap);

                $dasarPerhitunganFeeSales->created_by = 'System';
                $dasarPerhitunganFeeSales->created_at = $this->timestamp;

                $dasarPerhitunganFeeSales->save();

                // MUTASI FEE SALES
                Carbon::setLocale('id');

                $mutasiFeeSales = new MutasiFeeSales();

                $mutasiFeeSales->sales_id = $salesId;
                $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                $mutasiFeeSales->mutation_type = 'Kredit';
                $mutasiFeeSales->amount = $totalFee;
                $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->period)->translatedFormat('F Y');
                $mutasiFeeSales->status = 'Done';
                $mutasiFeeSales->created_by = 'System';
                $mutasiFeeSales->created_at = $this->timestamp;

                $mutasiFeeSales->save();

                // SALDO FEE SALES
                $saldoFeeSales = SaldoFeeSales::where('sales_id', $salesId)->latest()->first();
                if ($saldoFeeSales) {
                    $saldoFeeSales->active_balance += $totalFee;
                    $saldoFeeSales->updated_by = 'System';
                    $saldoFeeSales->updated_at = $this->timestamp;
                } else {
                    $saldoFeeSales = new SaldoFeeSales();
                    $saldoFeeSales->sales_id = $salesId;
                    $saldoFeeSales->active_balance = $totalFee;
                    $saldoFeeSales->created_by = 'System';
                    $saldoFeeSales->created_at = $this->timestamp;
                }
                $saldoFeeSales->save();
            }
            DB::commit();
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly Completed\n", Carbon::now());
        } catch (\Throwable $th) {
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly Error\n Line: %s\n Message: %s", Carbon::now(), $th->getLine(), $th->getMessage());
            DB::rollBack();
            Log::error('[FeeSalesMonthly] Error: ' . $th->getMessage() . ' Line: ' . $th->getLine());
        }
    }
}
