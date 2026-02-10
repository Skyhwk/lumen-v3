<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

class FeeSalesMonthly2
{
    private $cutOff;

    private $year;
    private $month;

    private $timestamp;

    private $periodRange;

    private $monthStr;

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

        $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();

        $this->periodRange = CarbonPeriod::create($this->cutOff, '1 month', $this->timestamp);

        $this->monthStr = self::INDO_MONTHS[$this->month];
    }

    public function run()
    {
        DB::beginTransaction();
        try {
            $masterTargetSales = MasterTargetSales::where(['tahun' => $this->year, 'is_active' => true])->whereNotNull($this->monthStr)->get();
            foreach ($masterTargetSales as $targetSales) {
                $salesId = $targetSales->karyawan_id;

                $feeSalesRecap = MasterFeeSales::where(['sales_id' => $salesId, 'is_active' => true])->get()->flatMap(fn($mfs) => collect(json_decode($mfs->recap, true)));
                $isExistsInFeeSales = fn($qsd) => $feeSalesRecap->contains(function ($recap) use ($qsd) {
                    if ($recap['no_order'] !== $qsd->no_order) return false;
                    if (!$qsd->periode) return $recap['is_lunas'] === $qsd->is_lunas;

                    return $recap['periode'] === $qsd->periode && $recap['is_lunas'] === $qsd->is_lunas;
                });

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

                if ($quotations->isEmpty()) continue;

                foreach ($this->periodRange as $period) {
                    $currentMonth = $period->format('m');
                    $currentPeriod = $period->format('Y-m');

                    $quotations = $quotations->filter(function ($qsd) use ($currentMonth) {
                        $tgl = Carbon::parse($qsd->tanggal_kelompok);

                        return $tgl->year == $this->year && $tgl->month == $currentMonth;
                    });

                    $masterFeeSales = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $currentPeriod, 'is_active' => true])->latest()->first();

                    $paidAchievedAmount = $quotations->sum('total_revenue_yg_lunas');

                    // TOTAL FEE
                    $claimedFee = $paidAchievedAmount * $masterFeeSales->basis;
                    $recap = collect(json_decode($masterFeeSales->recap, true))
                        ->map(function ($recap) use ($quotations) {
                            $match = $quotations->first(function ($qsd) use ($recap) {
                                if ($qsd->no_order !== $recap['no_order']) return false;
                                if ($qsd->periode) return $qsd->periode === $recap['periode'];
                                return true;
                            });

                            if ($match) $recap['is_lunas'] = 1;

                            return $recap;
                        });

                    $masterFeeSales->paid_achieved_amount += $paidAchievedAmount;
                    $masterFeeSales->claimed_fee += $claimedFee;
                    $masterFeeSales->recap = json_encode($recap);
                    $masterFeeSales->updated_by = 'System';
                    $masterFeeSales->updated_at = $this->timestamp;

                    $masterFeeSales->save();

                    // MUTASI FEE SALES
                    if ($claimedFee > 0) {
                        $mutasiFeeSales = new MutasiFeeSales();

                        $mutasiFeeSales->sales_id = $salesId;
                        $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                        $mutasiFeeSales->mutation_type = 'Kredit';
                        $mutasiFeeSales->amount = $claimedFee;
                        $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $currentPeriod)->translatedFormat('F Y');
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
                }
            }
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('[FeeSalesMonthly] Error: ' . $th->getMessage() . ' Line: ' . $th->getLine());
        }
    }
}
