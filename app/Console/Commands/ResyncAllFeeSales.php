<?php

namespace App\Console\Commands;

use App\Services\FeeSalesMutasiAligner;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Selaraskan semua (atau satu) sales: master dari rekap, mutasi kredit per periode (adjust in-place), saldo.
 */
class ResyncAllFeeSales extends Command
{
    protected $signature = 'fee-sales:resync-all
        {--sales-id= : Hanya satu sales}
        {--tolerance=1 : Selisih diabaikan jika <= Rp ini}
        {--as-of= : Tanggal tutup buku untuk updated_at master/saldo (YYYY-MM-DD); default akhir bulan kalender lalu}
        {--dry-run : Preview (default tanpa --apply)}
        {--apply : Tulis DB}
        {--backup : Wajib dengan --apply}';

    protected $description = 'Resync master + mutasi (adjust) + saldo agar selaras rekap lunas×basis';

    private Carbon $bookDate;

    public function handle(): int
    {
        $this->bookDate = $this->resolveBookDate();

        $apply = (bool) $this->option('apply');
        $tolerance = max(0.0, (float) $this->option('tolerance'));
        $filterSales = $this->option('sales-id') !== null && $this->option('sales-id') !== ''
            ? (int) $this->option('sales-id')
            : null;

        if ($apply && !$this->option('backup')) {
            $this->error('Gunakan --backup bersama --apply.');

            return 1;
        }

        $salesIds = DB::table('master_fee_sales')
            ->where('is_active', true)
            ->distinct()
            ->pluck('sales_id')
            ->when($filterSales, fn ($c) => $c->filter(fn ($id) => (int) $id === $filterSales))
            ->sort()
            ->values();

        if ($salesIds->isEmpty()) {
            $this->warn('Tidak ada sales fee aktif.');

            return 0;
        }

        $rows = [];
        $backupPayload = ['sales' => []];

        foreach ($salesIds as $salesId) {
            $salesId = (int) $salesId;
            $plan = $this->buildSalesPlan($salesId, $tolerance);
            $name = DB::table('master_karyawan')->where('id', $salesId)->value('nama_lengkap') ?? (string) $salesId;

            $rows[] = [
                $salesId,
                mb_substr($name, 0, 28),
                $plan['periods_fixed'],
                $this->rp($plan['saldo_before']),
                $this->rp($plan['saldo_after']),
                $this->rp($plan['gap_mutasi_before']),
                $this->rp($plan['gap_mutasi_after']),
            ];

            if ($apply && ($plan['periods_fixed'] > 0 || abs($plan['gap_mutasi_before']) > $tolerance)) {
                $backupPayload['sales'][$salesId] = $plan['backup'];
            }
        }

        $this->info($apply ? 'RESYNC FEE SALES — APPLY' : 'RESYNC FEE SALES — DRY-RUN');
        $this->line('Tanggal acuan (updated_at master/saldo): ' . $this->bookDate->format('Y-m-d H:i:s'));
        $this->table(
            ['ID', 'Nama', 'Periode di-adjust', 'Saldo sekarang', 'Saldo setelah', 'Gap mutasi↔rekap (sblm)', 'Gap (stlah)'],
            $rows
        );

        $this->newLine();
        $this->comment('Gap = mutasi kredit aktif − rekap lunas×basis. Setelah resync + adjust mutasi per periode, gap ≈ 0.');
        $this->comment('Rekap QSD lunas tapi is_lunas=0 tetap butuh: php artisan calculatefee:run (Phase 2).');

        if (!$apply) {
            $this->line('Contoh: php artisan fee-sales:resync-all --apply --backup');

            return 0;
        }

        $backupPath = storage_path('app/fee_resync_all_' . date('Ymd_His') . '.json');
        File::put($backupPath, json_encode($backupPayload, JSON_PRETTY_PRINT));
        $this->info("Backup: {$backupPath}");

        DB::transaction(function () use ($salesIds, $tolerance) {
            foreach ($salesIds as $salesId) {
                $this->applySalesPlan((int) $salesId, $tolerance, $this->bookDate);
            }
        });

        $this->info('Resync selesai.');

        return 0;
    }

    private function buildSalesPlan(int $salesId, float $tolerance): array
    {
        $masters = DB::table('master_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true])
            ->orderBy('period')
            ->get();

        $periodPlans = [];
        $mutasiBackup = [];

        foreach ($masters as $master) {
            $basis = (float) $master->basis;
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
                    'period' => $master->period,
                    'mutation_type' => 'Kredit',
                    'is_active' => true,
                ])
                ->orderBy('id')
                ->get(['id', 'amount'])
                ->map(fn ($r) => ['id' => (int) $r->id, 'amount' => (float) $r->amount])
                ->all();

            $mutasiSum = round(array_sum(array_column($mutasiRows, 'amount')), 2);
            $targetClaim = round($calcClaim, 2);
            $needsMutasiFix = $mutasiRows !== [] && abs($mutasiSum - $targetClaim) > $tolerance;

            $mutasiAfter = $needsMutasiFix
                ? FeeSalesMutasiAligner::distributeAmounts($mutasiRows, $targetClaim)
                : [];

            $periodPlans[] = [
                'master_id' => (int) $master->id,
                'period' => $master->period,
                'estimated_fee' => round($calcEst, 2),
                'claimed_fee' => $targetClaim,
                'paid_achieved_amount' => round($paidAchieved, 2),
                'mutasi_rows' => $mutasiRows,
                'mutasi_after' => $mutasiAfter,
                'needs_mutasi_fix' => $needsMutasiFix,
            ];

            if ($needsMutasiFix) {
                foreach ($mutasiRows as $row) {
                    $mutasiBackup[] = ['id' => $row['id'], 'amount' => $row['amount']];
                }
            }
        }

        $saldoBefore = (float) (DB::table('saldo_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => 1])
            ->orderByDesc('id')
            ->value('active_balance') ?? 0);

        $kredit = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Kredit'])
            ->sum('amount');
        $debit = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Debit'])
            ->sum('amount');

        $kreditAfter = $kredit;
        foreach ($periodPlans as $pp) {
            if (!$pp['needs_mutasi_fix']) {
                continue;
            }
            $before = array_sum(array_column($pp['mutasi_rows'], 'amount'));
            $after = array_sum($pp['mutasi_after']);
            $kreditAfter += $after - $before;
        }

        $rekapLunas = (float) DB::table('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.sales_id', $salesId)
            ->where('m.is_active', true)
            ->where('r.is_lunas', 1)
            ->sum(DB::raw('r.total_revenue * m.basis'));

        return [
            'periods' => $periodPlans,
            'periods_fixed' => collect($periodPlans)->where('needs_mutasi_fix', true)->count(),
            'saldo_before' => round($saldoBefore, 2),
            'saldo_after' => round($kreditAfter - $debit, 2),
            'gap_mutasi_before' => round($kredit - $rekapLunas, 2),
            'gap_mutasi_after' => round($kreditAfter - $rekapLunas, 2),
            'backup' => [
                'masters' => $masters->map(fn ($m) => (array) $m)->values()->all(),
                'mutasi' => $mutasiBackup,
                'saldo' => $saldoBefore,
            ],
        ];
    }

    private function applySalesPlan(int $salesId, float $tolerance, Carbon $bookDate): void
    {
        $plan = $this->buildSalesPlan($salesId, $tolerance);

        foreach ($plan['periods'] as $pp) {
            DB::table('master_fee_sales')->where('id', $pp['master_id'])->update([
                'estimated_fee' => $pp['estimated_fee'],
                'claimed_fee' => $pp['claimed_fee'],
                'paid_achieved_amount' => $pp['paid_achieved_amount'],
                'updated_by' => 'System',
                'updated_at' => $bookDate,
            ]);

            if ($pp['needs_mutasi_fix']) {
                foreach ($pp['mutasi_after'] as $mutasiId => $amount) {
                    DB::table('mutasi_fee_sales')->where('id', $mutasiId)->update(['amount' => $amount]);
                }
            }
        }

        DB::table('saldo_fee_sales')->where(['sales_id' => $salesId, 'is_active' => true])
            ->orderByDesc('id')->limit(1)
            ->update([
                'active_balance' => $plan['saldo_after'],
                'updated_by' => 'System',
                'updated_at' => $bookDate,
            ]);
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
        return number_format($value, 2, ',', '.');
    }
}
