<?php

namespace App\Services;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\{
    ClaimFeeExternal,
    // MasterKaryawan,
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

    public function __construct()
    {
        $this->currentYear = Carbon::now()->year;
        $this->currentMonth =  Carbon::now()->format('m');
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
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly\n", Carbon::now()->format('Y-m-d H:i:s'));

            $masterTargetSales = MasterTargetSales::where('tahun', $this->currentYear)->where('is_active', true)->whereNotNull($this->currentMonthStr)->get();
            $categoryStr = config('kategori.id');
            foreach ($masterTargetSales as $targetSales) {
                $salesId = $targetSales->karyawan_id;

                $masterFeeSalesExists = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $this->currentPeriod])->exists();
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
                    ->whereDate('tanggal_kelompok', '<=', Carbon::create($this->currentYear, $this->currentMonth)->endOfMonth())
                    ->where('is_lunas', true)
                    ->get()
                    ->map(function ($qsd) use ($isExistsInFeeSales) {
                        if ($isExistsInFeeSales($qsd)) return null;

                        $totalFeeExternal = ClaimFeeExternal::where('no_order', $qsd->no_order)->when($qsd->periode, fn ($q) =>$q->where('periode', $qsd->periode))->where('is_active', true)->sum('nominal');

                        $nilai_pengurangan = $qsd->nilai_pengurangan;
                        $nominal_potong = ($totalFeeExternal + $nilai_pengurangan);
                        $qsd->total_revenue -= $nominal_potong;

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
                
                printf("[FeeSalesMonthly] [%s] Calculate Data Completed\n", Carbon::now()->format('Y-m-d H:i:s'));
                
                if ($quotations->isEmpty()) continue;

                printf("[FeeSalesMonthly] [%s] Find ".$quotations->count()." Data\n", Carbon::now()->format('Y-m-d H:i:s'));
                printf("[FeeSalesMonthly] [%s] Start Mapping Data\n", Carbon::now()->format('Y-m-d H:i:s'));

                // FEE AMOUNT
                $targetAmount = json_decode($targetSales->target, true)[$this->currentPeriod];
                $achievedAmount = $quotations->sum('total_revenue');
                $percentageAmount = $achievedAmount / $targetAmount;
                $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                // $feeAmount = $achievedAmount * $rate;

                // FEE CATEGORY
                $targetCategory = collect($targetSales->{$this->currentMonthStr});
                $achievedCategoryDetails = $targetCategory->map(
                    function ($_, $category) use ($quotations, $targetCategory, $categoryStr) {
                        $target = $targetCategory[$category];

                        $achieved = $quotations->flatMap(fn($q) => optional($q->orderHeader)->orderDetail)
                            ->filter(fn($orderDetail) => collect($categoryStr[$category])->contains($orderDetail->kategori_3))
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

                // TOTAL FEE
                $totalFee = $totalAchievedPoint / $totalTargetPoint * $rate * $achievedAmount;

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
                
                printf("[FeeSalesMonthly] [%s] Mapping Data Completed\n", Carbon::now()->format('Y-m-d H:i:s'));
                
                printf("[FeeSalesMonthly] [%s] Start Insert Data for period %s\n", Carbon::now()->format('Y-m-d H:i:s'), $this->currentPeriod);
                // MASTER FEE SALES
                $masterFeeSales = new MasterFeeSales();

                $masterFeeSales->sales_id = $salesId;
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

                $mutasiFeeSales->sales_id = $salesId;
                $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                $mutasiFeeSales->mutation_type = 'Kredit';
                $mutasiFeeSales->amount = $totalFee;
                $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $this->currentPeriod)->translatedFormat('F Y');
                $mutasiFeeSales->status = 'Done';
                $mutasiFeeSales->created_at = Carbon::create($this->currentYear, $this->currentMonth)->endOfMonth();
                $mutasiFeeSales->created_by = 'System';
                $mutasiFeeSales->updated_by = 'System';

                $mutasiFeeSales->save();

                // SALDO FEE SALES
                $saldoFeeSales = SaldoFeeSales::where('sales_id', $salesId)->latest()->first();
                if ($saldoFeeSales) {
                    $saldoFeeSales->active_balance += $totalFee;
                } else {
                    $saldoFeeSales = new SaldoFeeSales();
                    $saldoFeeSales->sales_id = $salesId;
                    $saldoFeeSales->active_balance = $totalFee;
                }
                $saldoFeeSales->created_at = Carbon::create($this->currentYear, $this->currentMonth)->endOfMonth();
                $saldoFeeSales->updated_by = 'System';
                $saldoFeeSales->created_by = 'System';
                $saldoFeeSales->save();

                printf("[FeeSalesMonthly] [%s] Insert Data Completed\n", Carbon::now()->format('Y-m-d H:i:s'));
            }
            DB::commit();
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly Completed\n", Carbon::now()->format('Y-m-d H:i:s'));
        } catch (\Throwable $th) {
            printf("[FeeSalesMonthly] [%s] Running Fee Sales Monthly Error\n Line: %s\n Message: %s", Carbon::now()->format('Y-m-d H:i:s'), $th->getLine(), $th->getMessage());
            DB::rollBack();
            Log::error('[FeeSalesMonthly] Error: ' . $th->getMessage() . ' Line: ' . $th->getLine());
        }
    }
}
