<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\{
    MasterTargetSales,
    DailyQsd,
    MasterFeeSales,
    ClaimFeeExternal,
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

    public function run($periode = NULL)
    {
        if ($periode) {
            $arrMonth = explode('-', $periode);
            $this->year = $arrMonth[0];
            $this->month = $arrMonth[1];
            $this->period = $periode;
            $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();
            $this->monthStr = self::INDO_MONTHS[$this->month];
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly For %s \n", date('Y-m-d H:i:s'), $this->period);
        } else {
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly For %s \n", date('Y-m-d H:i:s'), $this->period);
        }

        DB::beginTransaction();
        try {
            printf("[FeeSalesMonthly] [%s] get master target sales \n", date('Y-m-d H:i:s'));
            $masterTargetSales = MasterTargetSales::where(['tahun' => $this->year, 'is_active' => true])->whereNotNull($this->monthStr)->get();
            printf("[FeeSalesMonthly] [%s] get master target sales done \n", date('Y-m-d H:i:s'));
            printf("[FeeSalesMonthly] [%s] start looping master target sales \n", date('Y-m-d H:i:s'));
            foreach ($masterTargetSales as $targetSales) {
                printf("[FeeSalesMonthly] [%s] start looping master target sales for sales id %s \n", date('Y-m-d H:i:s'), $targetSales->karyawan_id);
                $salesId = $targetSales->karyawan_id;

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $this->period, 'is_active' => true])->exists();
                printf("[FeeSalesMonthly] [%s] master fee sales exists for sales id %s \n", date('Y-m-d H:i:s'), $salesId);
                if ($masterFeeSalesExists) continue;

                $feeSalesRecap = MasterFeeSales::where(['sales_id' => $salesId, 'is_active' => true])->get()->flatMap(fn($mfs) => collect(json_decode($mfs->recap, true)));
                $isExistsInFeeSales = fn($qsd) => $feeSalesRecap->contains(function ($recap) use ($qsd) {
                    if ($recap['no_order'] !== $qsd->no_order) return false;
                    if (!$qsd->periode) return true;

                    return $recap['periode'] === $qsd->periode;
                });
                printf("[FeeSalesMonthly] [%s] is exists in fee sales done \n", date('Y-m-d H:i:s'));
                printf("[FeeSalesMonthly] [%s] start getting quotations \n", date('Y-m-d H:i:s'));
                $quotations = DailyQsd::with('orderHeader.orderDetail')
                    ->where('sales_id', $salesId)
                    ->whereDate('tanggal_kelompok', '>=', $this->cutOff)
                    ->whereDate('tanggal_kelompok', '<=', $this->timestamp)
                    ->get()
                    ->map(function ($qsd) use ($isExistsInFeeSales) {
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
                    })
                    ->filter()
                    ->values();
                printf("[FeeSalesMonthly] [%s] get quotations done \n", date('Y-m-d H:i:s'));
                if ($quotations->isEmpty()) continue;
                printf("[FeeSalesMonthly] [%s] Start Calculating Achievement \n", date('Y-m-d H:i:s'));

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

                // RECAP
                $recap = $quotations->map(fn($quotation) => [
                    'no_document' => $quotation->no_quotation,
                    'no_order' => $quotation->no_order,
                    'nama_perusahaan' => $quotation->nama_perusahaan,
                    'periode' => $quotation->periode,
                    'kategori_3' => optional($quotation->orderHeader)->orderDetail ? $quotation->orderHeader->orderDetail->pluck('kategori_3')->toArray() : [],
                    'no_invoice' => $quotation->no_invoice,
                    'is_lunas' => $quotation->is_lunas,
                    'total_revenue' => $quotation->total_revenue,
                ])->values();
                printf("[FeeSalesMonthly] [%s] Calculating Achievement Done \n", date('Y-m-d H:i:s'));
                printf("[FeeSalesMonthly] [%s] Start Inserting Data \n", date('Y-m-d H:i:s'));
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
                $masterFeeSales->basis = $basis;
                $masterFeeSales->paid_achieved_amount = $paidAchievedAmount;
                $masterFeeSales->estimated_fee = $estimatedFee;
                $masterFeeSales->claimed_fee = $claimedFee;
                $masterFeeSales->recap = json_encode($recap);
                $masterFeeSales->created_by = 'System';
                $masterFeeSales->created_at = $this->timestamp;

                $masterFeeSales->save();

                // MUTASI FEE SALES
                if ($claimedFee > 0) {
                    $mutasiFeeSales = new MutasiFeeSales();

                    $mutasiFeeSales->sales_id = $salesId;
                    $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                    $mutasiFeeSales->mutation_type = 'Kredit';
                    $mutasiFeeSales->amount = $claimedFee;
                    $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->period)->translatedFormat('F Y');
                    $mutasiFeeSales->status = 'Done';
                    $mutasiFeeSales->created_by = 'System';
                    $mutasiFeeSales->created_at = $this->timestamp;

                    $mutasiFeeSales->save();
                }

                // SALDO FEE SALES
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
                printf("[FeeSalesMonthly] [%s] Inserting Data Done \n", date('Y-m-d H:i:s'));
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[FeeSalesMonthly] Error: ' . $th->getMessage() . ' Line: ' . $th->getLine());
        }
    }
}
