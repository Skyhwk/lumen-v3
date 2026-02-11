<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

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

            $this->timestamp = Carbon::create($this->year, $this->month)->endOfMonth();

            $this->periodRange = CarbonPeriod::create($this->cutOff, '1 month', $this->timestamp);

            $this->monthStr = self::INDO_MONTHS[$this->month];
        }

        printf("\n\033[1;35m========================================================================\033[0m\n");
        printf("\033[1;35m  FEE SALES (PHASE 2) - CHECKING PAID STATUS UPDATES \033[0m\n");
        printf("\033[1;35m========================================================================\033[0m\n");

        $this->log("Checking updates from: " . $this->cutOff->format('Y-m-d') . " to " . $this->timestamp->format('Y-m-d'));

        DB::beginTransaction();
        try {
            $masterTargetSales = MasterTargetSales::with('sales:id,nama_lengkap')->where(['tahun' => $this->year, 'is_active' => true])->whereNotNull($this->monthStr)->get();
            foreach ($masterTargetSales as $targetSales) {
                $salesId = $targetSales->karyawan_id;
                $salesName = $targetSales->sales->nama_lengkap;

                $existingRecaps = RekapFeeSales::whereHas('masterFeeSales', fn($q) => $q->where(['sales_id' => $salesId, 'is_active' => true]))->get();

                $isExistsInFeeSales = fn($qsd) => $existingRecaps->contains(function ($recap) use ($qsd) {
                    if ($recap->no_order !== $qsd->no_order) return false;

                    if (!$qsd->periode) return $recap->is_lunas == $qsd->is_lunas;
                    return $recap->periode === $qsd->periode && $recap->is_lunas == $qsd->is_lunas;
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

                printf("\n>>> Found %d items with updated payment status for Sales: %s\n", $quotations->count(), $salesName);

                foreach ($this->periodRange as $period) {
                    $currentMonth = $period->format('m');
                    $currentPeriod = $period->format('Y-m');

                    $quotations = $quotations->filter(function ($qsd) use ($currentMonth) {
                        $tgl = Carbon::parse($qsd->tanggal_kelompok);

                        return $tgl->year == $this->year && $tgl->month == $currentMonth;
                    });

                    $masterFeeSales = MasterFeeSales::where(['sales_id' => $salesId, 'period' => $currentPeriod, 'is_active' => true])->latest()->first();
                    if (!$masterFeeSales) {
                        $this->log("No MasterFeeSales found for $currentPeriod. Skipping update.", "WARNING");
                        continue;
                    }

                    $paidAchievedAmount = $quotations->sum('total_revenue_yg_lunas');

                    // TOTAL FEE
                    $claimedFee = $paidAchievedAmount * $masterFeeSales->basis;

                    if ($claimedFee > 0) {
                        $this->log("Updating Fee Sales for period $currentPeriod ...");

                        $masterFeeSales->paid_achieved_amount += $paidAchievedAmount;
                        $masterFeeSales->claimed_fee += $claimedFee;
                        $masterFeeSales->updated_by = 'System';
                        $masterFeeSales->updated_at = $this->timestamp;

                        $masterFeeSales->save();

                        // REKAP FEE SALES
                        $this->log("Updating Recaps ...");
                        foreach ($quotations as $qsd) {
                            $recap = RekapFeeSales::where(['fee_sales_id' => $masterFeeSales->id, 'no_order' => $qsd->no_order])->when($qsd->periode, fn($q) => $q->where('periode', $qsd->periode))->first();

                            if ($recap) {
                                $recap->is_lunas = true;
                                $recap->save();
                            }
                        }

                        // MUTASI FEE SALES
                        $this->log("Creating Mutation Record ...", "INFO");
                        $mutasiFeeSales = new MutasiFeeSales();

                        $mutasiFeeSales->sales_id = $salesId;
                        $mutasiFeeSales->batch_number = str_replace('.', '/', microtime(true));
                        $mutasiFeeSales->period = $currentPeriod;
                        $mutasiFeeSales->mutation_type = 'Kredit';
                        $mutasiFeeSales->amount = $claimedFee;
                        $mutasiFeeSales->description = 'Fee Sales ' . Carbon::createFromFormat('Y-m', $currentPeriod)->translatedFormat('F Y');
                        $mutasiFeeSales->status = 'Done';
                        $mutasiFeeSales->created_by = 'System';
                        $mutasiFeeSales->created_at = $this->timestamp;

                        $mutasiFeeSales->save();

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
                        $this->log("Fee Sales updated successfully", "SUCCESS");
                    }
                }
            }
            DB::commit();

            printf("\n");
            $this->log("All Done", "SUCCESS");
        } catch (\Throwable $th) {
            DB::rollBack();

            printf("\n");
            $this->log("Error: " . $th->getMessage() . " in Line " . $th->getLine(), "ERROR");
            $this->log("Transaction Rolled Back", "ERROR");
        }
    }
}
