<?php

namespace App\Console\Commands;

use App\Models\MasterTargetSales;
use App\Services\FeeSalesMutasiAligner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Hitung ulang master fee satu periode dari rekap (+ opsional basis dari target terbaru),
 * selaraskan saldo. Default: hanya ubah kolom amount pada mutasi kredit yang sudah ada (tanpa baris baru).
 */
class RecalculateFeeSalesMaster extends Command
{
    protected $signature = 'fee-sales:recalculate-master
        {--sales-id= : Wajib}
        {--period= : YYYY-MM master yang dihitung ulang}
        {--recompute-basis : Basis/rate dari master_target_sales + data master (tanpa ubah rekap)}
        {--resync-all-masters : Setelah apply, sync estimated/claimed semua master sales dari rekap}
        {--mutasi-mode=adjust : adjust = ubah nominal mutasi lama (default) | replace = nonaktifkan lama + 1 mutasi baru}
        {--as-of= : Tanggal tutup buku untuk updated_at (default akhir bulan kalender lalu, mis. 31 Okt)}
        {--dry-run : Default: hanya preview}
        {--apply : Tulis ke DB}
        {--backup : Wajib dengan --apply}';

    protected $description = 'Hitung ulang master fee periode (koreksi over-kredit target) + mutasi & saldo';

    private Carbon $bookDate;

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

    public function handle(): int
    {
        $this->bookDate = $this->resolveBookDate();

        $salesId = (int) $this->option('sales-id');
        $period = (string) $this->option('period');
        $apply = (bool) $this->option('apply');
        $dryRun = !$apply;

        if (!$salesId || !$period || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            $this->error('Wajib --sales-id= dan --period=YYYY-MM');

            return 1;
        }

        if ($apply && !$this->option('backup')) {
            $this->error('Gunakan --backup bersama --apply.');

            return 1;
        }

        $master = DB::table('master_fee_sales')
            ->where(['sales_id' => $salesId, 'period' => $period, 'is_active' => true])
            ->first();

        if (!$master) {
            $this->error("Master fee tidak ditemukan: sales={$salesId} period={$period}");

            return 1;
        }

        $mutasiMode = strtolower((string) $this->option('mutasi-mode'));
        if (!in_array($mutasiMode, ['adjust', 'replace'], true)) {
            $this->error('--mutasi-mode harus adjust atau replace');

            return 1;
        }

        $nama = DB::table('master_karyawan')->where('id', $salesId)->value('nama_lengkap') ?? '-';
        $plan = $this->buildPlan(
            $salesId,
            $master,
            (bool) $this->option('recompute-basis'),
            (bool) $this->option('resync-all-masters'),
            $mutasiMode
        );

        $this->info("Rekalkulasi fee — {$nama} (id={$salesId}) periode {$period}");
        $this->info($dryRun ? 'Mode: DRY-RUN' : 'Mode: APPLY');
        $this->line('Tanggal acuan (updated_at): ' . $this->bookDate->format('Y-m-d H:i:s'));
        $this->line('Mutasi: ' . ($mutasiMode === 'adjust'
            ? 'sesuaikan nominal baris yang sudah ada (deskripsi/tanggal tetap, tanpa mutasi baru)'
            : 'ganti dengan satu mutasi baru (terlihat di UI)'));

        $this->table(
            ['', 'Sekarang', 'Setelah hitung ulang'],
            [
                ['Basis', number_format((float) $master->basis, 8, ',', '.'), number_format($plan['basis'], 8, ',', '.')],
                ['Estimated fee', $this->rp((float) $master->estimated_fee), $this->rp($plan['estimated_fee'])],
                ['Claimed fee (master)', $this->rp((float) $master->claimed_fee), $this->rp($plan['claimed_fee'])],
                ['Mutasi kredit periode', $this->rp($plan['mutasi_period_before']), $this->rp($plan['claimed_fee'])],
                ['Rekap lunas×basis', $this->rp($plan['rekap_claim_calc']), $this->rp($plan['claimed_fee'])],
            ]
        );

        $this->line('Mutasi kredit periode ini:');
        foreach ($plan['mutasi_rows'] as $row) {
            $after = $plan['mutasi_amount_after'][$row['id']] ?? $row['amount'];
            $this->line(sprintf(
                '  id=%d  %s → %s  | %s',
                $row['id'],
                $this->rp($row['amount']),
                $this->rp($after),
                $row['description']
            ));
        }
        if (empty($plan['mutasi_rows']) && $plan['claimed_fee'] > 0) {
            $this->warn('  Tidak ada mutasi kredit periode; saldo/master tetap diselaraskan (pertimbangkan replace).');
        }

        $this->newLine();
        $this->line('Selisih mutasi periode vs rekap lunas: ' . $this->rp($plan['mutasi_period_before'] - $plan['claimed_fee']));
        $this->line('Saldo aktif: ' . $this->rp($plan['saldo_before']) . ' → ' . $this->rp($plan['saldo_after']));
        $this->line('Rekap lunas global vs mutasi kredit global: ' . $this->rp($plan['global_rekap_lunas'])
            . ' vs ' . $this->rp($plan['global_mutasi_after']) . ' (gap ' . $this->rp($plan['global_gap_after']) . ')');

        if ($dryRun) {
            $this->comment('Tanpa perubahan DB. Contoh:');
            $this->line("  php artisan fee-sales:recalculate-master --sales-id={$salesId} --period={$period} --resync-all-masters --apply --backup");

            return 0;
        }

        $backupPath = storage_path(
            'app/fee_recalc_' . $salesId . '_' . str_replace('-', '', $period) . '_' . date('Ymd_His') . '.json'
        );
        File::put($backupPath, json_encode($plan['backup'], JSON_PRETTY_PRINT));
        $this->info("Backup: {$backupPath}");

        DB::transaction(function () use ($plan, $master, $salesId, $period) {
            $bookDate = $this->bookDate;
            $updates = [
                'estimated_fee' => $plan['estimated_fee'],
                'claimed_fee' => $plan['claimed_fee'],
                'paid_achieved_amount' => $plan['paid_achieved_amount'],
                'updated_by' => 'System',
                'updated_at' => $bookDate,
            ];
            if ($plan['basis_updated']) {
                $updates['basis'] = $plan['basis'];
                $updates['rate'] = $plan['rate'];
                $updates['percentage_amount'] = $plan['percentage_amount'];
                $updates['target_amount'] = $plan['target_amount'];
            }

            DB::table('master_fee_sales')->where('id', $master->id)->update($updates);

            if ($plan['mutasi_mode'] === 'adjust') {
                foreach ($plan['mutasi_amount_after'] as $mutasiId => $amount) {
                    DB::table('mutasi_fee_sales')->where('id', $mutasiId)->update([
                        'amount' => $amount,
                    ]);
                }
            } else {
                foreach ($plan['mutasi_rows'] as $row) {
                    DB::table('mutasi_fee_sales')->where('id', $row['id'])->update(['is_active' => 0]);
                }
                if ($plan['claimed_fee'] > 0) {
                    DB::table('mutasi_fee_sales')->insert([
                        'sales_id' => $salesId,
                        'batch_number' => str_replace('.', '/', (string) microtime(true)),
                        'period' => $period,
                        'mutation_type' => 'Kredit',
                        'amount' => $plan['claimed_fee'],
                        'description' => 'Fee Sales ' . $this->periodLabel($period),
                        'status' => 'Done',
                        'is_active' => 1,
                        'created_by' => 'System',
                        'created_at' => $bookDate,
                    ]);
                }
            }

            if ($plan['resync_masters']) {
                $masters = DB::table('master_fee_sales')
                    ->where(['sales_id' => $salesId, 'is_active' => true])
                    ->get();
                foreach ($masters as $m) {
                    $basis = (float) $m->basis;
                    $calcEst = (float) DB::table('rekap_fee_sales')->where('fee_sales_id', $m->id)
                        ->sum(DB::raw('total_revenue * ' . $basis));
                    $calcClaim = (float) DB::table('rekap_fee_sales')
                        ->where(['fee_sales_id' => $m->id, 'is_lunas' => 1])
                        ->sum(DB::raw('total_revenue * ' . $basis));
                    DB::table('master_fee_sales')->where('id', $m->id)->update([
                        'estimated_fee' => $calcEst,
                        'claimed_fee' => $calcClaim,
                        'updated_by' => 'System',
                        'updated_at' => $bookDate,
                    ]);
                }
            }

            DB::table('saldo_fee_sales')->where(['sales_id' => $salesId, 'is_active' => true])
                ->orderByDesc('id')->limit(1)
                ->update([
                    'active_balance' => $plan['saldo_after'],
                    'updated_by' => 'System',
                    'updated_at' => $bookDate,
                ]);
        });

        $this->info('Rekalkulasi selesai.');

        return 0;
    }

    private function buildPlan(int $salesId, object $master, bool $recomputeBasis, bool $resyncAllMasters, string $mutasiMode): array
    {
        $period = $master->period;
        $basis = (float) $master->basis;
        $rate = (float) $master->rate;
        $targetAmount = (float) $master->target_amount;
        $percentageAmount = (float) $master->percentage_amount;
        $basisUpdated = false;

        if ($recomputeBasis) {
            [$year, $month] = explode('-', $period);
            $monthStr = self::INDO_MONTHS[$month];
            $targetSales = MasterTargetSales::where([
                'karyawan_id' => $salesId,
                'tahun' => $year,
                'is_active' => true,
            ])->first();

            if ($targetSales && $targetSales->{$monthStr} !== null) {
                $targetAmount = (float) (json_decode($targetSales->target, true)[$period] ?? $targetAmount);
                $achievedAmount = (float) $master->achieved_amount;
                $rate = ($achievedAmount >= $targetAmount ? 5 : 1) / 100;
                $percentageAmount = $targetAmount > 0 ? $achievedAmount / $targetAmount : 0;
                $percentageCategory = (float) $master->percentage_category;
                $basis = $rate * $percentageCategory;
                $basisUpdated = true;
            } else {
                $this->warn('recompute-basis: target tidak ditemukan, pakai basis master existing.');
            }
        }

        $calcEst = (float) DB::table('rekap_fee_sales')->where('fee_sales_id', $master->id)
            ->sum(DB::raw('total_revenue * ' . $basis));
        $calcClaim = (float) DB::table('rekap_fee_sales')
            ->where(['fee_sales_id' => $master->id, 'is_lunas' => 1])
            ->sum(DB::raw('total_revenue * ' . $basis));
        $paidAchieved = (float) DB::table('rekap_fee_sales')
            ->where(['fee_sales_id' => $master->id, 'is_lunas' => 1])
            ->sum('total_revenue');

        $mutasiRows = DB::table('mutasi_fee_sales')
            ->where([
                'sales_id' => $salesId,
                'period' => $period,
                'mutation_type' => 'Kredit',
                'is_active' => true,
            ])
            ->orderBy('id')
            ->get(['id', 'amount', 'description'])
            ->map(fn ($r) => ['id' => $r->id, 'amount' => (float) $r->amount, 'description' => (string) $r->description])
            ->all();

        $mutasiPeriodBefore = array_sum(array_column($mutasiRows, 'amount'));
        $claimedRounded = round($calcClaim, 2);
        $mutasiAmountAfter = FeeSalesMutasiAligner::distributeAmounts(
            array_map(fn ($r) => ['id' => $r['id'], 'amount' => $r['amount']], $mutasiRows),
            $claimedRounded
        );

        $saldoBefore = (float) (DB::table('saldo_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => 1])
            ->orderByDesc('id')
            ->value('active_balance') ?? 0);

        $kreditAll = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Kredit'])
            ->sum('amount');
        $debitAll = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Debit'])
            ->sum('amount');

        $mutasiPeriodAfter = array_sum($mutasiAmountAfter);
        $kreditAllAfter = $kreditAll - $mutasiPeriodBefore + $mutasiPeriodAfter;
        $saldoAfter = round($kreditAllAfter - $debitAll, 2);

        $rekapLunasGlobal = (float) DB::table('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.sales_id', $salesId)
            ->where('m.is_active', true)
            ->where('r.is_lunas', 1)
            ->sum(DB::raw('r.total_revenue * m.basis'));

        if ($resyncAllMasters) {
            $masters = DB::table('master_fee_sales')->where(['sales_id' => $salesId, 'is_active' => true])->get();
            $rekapLunasGlobal = 0.0;
            foreach ($masters as $m) {
                if ((int) $m->id === (int) $master->id) {
                    $rekapLunasGlobal += $calcClaim;
                } else {
                    $rekapLunasGlobal += (float) DB::table('rekap_fee_sales')
                        ->where(['fee_sales_id' => $m->id, 'is_lunas' => 1])
                        ->sum(DB::raw('total_revenue * ' . (float) $m->basis));
                }
            }
        }

        return [
            'basis' => $basis,
            'rate' => $rate,
            'target_amount' => $targetAmount,
            'percentage_amount' => $percentageAmount,
            'basis_updated' => $basisUpdated,
            'estimated_fee' => round($calcEst, 2),
            'claimed_fee' => $claimedRounded,
            'paid_achieved_amount' => round($paidAchieved, 2),
            'mutasi_period_before' => round($mutasiPeriodBefore, 2),
            'rekap_claim_calc' => round($calcClaim, 2),
            'mutasi_rows' => $mutasiRows,
            'mutasi_amount_after' => $mutasiAmountAfter,
            'mutasi_mode' => $mutasiMode,
            'saldo_before' => round($saldoBefore, 2),
            'saldo_after' => $saldoAfter,
            'global_rekap_lunas' => round($rekapLunasGlobal, 2),
            'global_mutasi_after' => round($kreditAllAfter, 2),
            'global_gap_after' => round($kreditAllAfter - $rekapLunasGlobal, 2),
            'resync_masters' => $resyncAllMasters,
            'backup' => [
                'master' => (array) $master,
                'mutasi' => $mutasiRows,
                'saldo' => $saldoBefore,
            ],
        ];
    }

    private function periodLabel(string $period): string
    {
        return Carbon::createFromFormat('Y-m', $period)->locale('id')->translatedFormat('F Y');
    }

    private function resolveBookDate(): Carbon
    {
        $asOf = $this->option('as-of');
        if ($asOf !== null && $asOf !== '') {
            return Carbon::parse($asOf)->endOfDay();
        }

        return Carbon::now()->subMonth()->endOfMonth()->endOfDay();
    }

    private function rp(float $value): string
    {
        return 'Rp ' . number_format($value, 2, ',', '.');
    }
}
