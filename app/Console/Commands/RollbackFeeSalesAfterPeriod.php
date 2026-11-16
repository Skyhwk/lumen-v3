<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class RollbackFeeSalesAfterPeriod extends Command
{
    protected $signature = 'fee-sales:rollback-after-period
        {--sales-id= : Wajib; satu sales}
        {--through= : Periode terakhir yang dipertahankan, format YYYY-MM (contoh 2026-09)}
        {--dry-run : Hanya tampilkan rencana, tanpa menulis DB (default jika tanpa --apply)}
        {--apply : Jalankan rollback ke database}
        {--backup : Wajib dipakai bersama --apply; simpan JSON sebelum apply}
        {--rollback= : Pulihkan state dari file JSON hasil --apply --backup}
        {--clear-target-months : Kosongkan kolom target bulan setelah --through di master_target_sales}';

    protected $description = 'Audit/rekonsiliasi fee sales: simulasi posisi akhir periode (--through) lalu opsional apply rollback';

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

    private const INDO_MONTH_LABELS = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember',
    ];

    public function handle()
    {
        $rollbackFile = $this->option('rollback');
        if ($rollbackFile) {
            $apply = (bool) $this->option('apply');
            if ($apply && $this->option('dry-run')) {
                $this->error('Restore: pilih --dry-run atau --apply, jangan keduanya.');
                return 1;
            }
            return $this->restoreFromBackup($rollbackFile, !$apply);
        }

        $salesId = (int) $this->option('sales-id');
        $through = $this->option('through');
        $apply = (bool) $this->option('apply');
        $dryRunExplicit = $this->option('dry-run');
        if ($apply && $dryRunExplicit) {
            $this->error('Pilih salah satu: --dry-run atau --apply (jangan keduanya).');
            return 1;
        }
        $dryRun = !$apply;

        if (!$salesId) {
            $this->error('Wajib --sales-id= (contoh 779).');
            return 1;
        }
        if (!$through || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $through)) {
            $this->error('Wajib --through=YYYY-MM (contoh 2026-09).');
            return 1;
        }

        if ($apply && !$this->option('backup')) {
            $this->error('Gunakan --backup bersama --apply.');
            return 1;
        }

        $nama = DB::table('master_karyawan')->where('id', $salesId)->value('nama_lengkap') ?? '-';

        $masters = DB::table('master_fee_sales')
            ->where(['sales_id' => $salesId])
            ->where('period', '>', $through)
            ->orderBy('period')
            ->get();

        $masterIds = $masters->pluck('id')->all();
        $rekapCount = $masterIds
            ? DB::table('rekap_fee_sales')->whereIn('fee_sales_id', $masterIds)->count()
            : 0;

        $labelsAfter = $this->monthLabelsAfter($through);

        $mutasi = DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true])
            ->orderBy('id')
            ->get()
            ->filter(function ($row) use ($through, $labelsAfter) {
                if ($row->period && $this->periodGreater($row->period, $through)) {
                    return true;
                }
                $desc = (string) $row->description;
                foreach ($labelsAfter as $label) {
                    if (stripos($desc, 'Fee Sales ' . $label) !== false) {
                        return true;
                    }
                }
                return false;
            })
            ->values();

        $mutasiKredit = $mutasi->where('mutation_type', 'Kredit')->sum('amount');
        $mutasiDebit = $mutasi->where('mutation_type', 'Debit')->sum('amount');

        $saldo = DB::table('saldo_fee_sales')->where(['sales_id' => $salesId, 'is_active' => 1])->orderByDesc('id')->first();
        $saldoBefore = $saldo ? (float) $saldo->active_balance : 0;

        $kreditRemaining = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Kredit'])
            ->whereNotIn('id', $mutasi->pluck('id')->all())
            ->sum('amount');
        $debitRemaining = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Debit'])
            ->whereNotIn('id', $mutasi->pluck('id')->all())
            ->sum('amount');
        $saldoAfter = round($kreditRemaining - $debitRemaining, 2);

        $target = DB::table('master_target_sales')
            ->where(['karyawan_id' => $salesId, 'tahun' => substr($through, 0, 4), 'is_active' => true])
            ->first();
        $targetCols = $this->targetColumnsAfter($through);

        $remainingMasters = DB::table('master_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true])
            ->where('period', '<=', $through)
            ->orderBy('period')
            ->get();

        $cutoffLabel = Carbon::createFromFormat('Y-m', $through)->endOfMonth()->format('d M Y');

        $this->info('');
        $this->info('========================================================================');
        $this->info($dryRun
            ? '  AUDIT / REKONSILIASI FEE SALES — DRY-RUN (tidak mengubah DB)'
            : '  ROLLBACK FEE SALES — APPLY');
        $this->info("  Sales: {$nama} (id={$salesId})");
        $this->info("  Posisi akhir buku: {$through} (s/d {$cutoffLabel})");
        if ($dryRun) {
            $this->comment('  Membandingkan DB sekarang vs simulasi setelah buang hitungan bulan setelah ' . $through . '.');
            $this->comment('  Ini bukan menjalankan calculatefee — hanya rekonsiliasi data.');
        }
        $this->info('========================================================================');

        $this->warn('Master fee yang akan dihapus (period > ' . $through . '):');
        if ($masters->isEmpty()) {
            $this->line('  (tidak ada)');
        } else {
            foreach ($masters as $m) {
                $this->line(sprintf(
                    '  id=%d period=%s est=%s claimed=%s',
                    $m->id,
                    $m->period,
                    number_format($m->estimated_fee, 2, ',', '.'),
                    number_format($m->claimed_fee, 2, ',', '.')
                ));
            }
        }
        $this->line("  Rekap terkait: {$rekapCount} baris");

        $this->warn('Mutasi yang akan dinonaktifkan (period > through atau label bulan setelahnya):');
        if ($mutasi->isEmpty()) {
            $this->line('  (tidak ada)');
        } else {
            foreach ($mutasi as $m) {
                $this->line(sprintf(
                    '  id=%d period=%s %s %s | %s',
                    $m->id,
                    $m->period,
                    $m->mutation_type,
                    number_format($m->amount, 2, ',', '.'),
                    $m->description
                ));
            }
        }
        $this->line('  Total kredit mutasi dihapus: ' . number_format($mutasiKredit, 2, ',', '.'));

        $summaryDbSekarang = $this->feeSummary($salesId);
        $summaryRekapAkhirBuku = $this->feeSummary($salesId, [
            'only_period_lte' => $through,
        ]);
        $summarySetelahRollback = $this->feeSummary($salesId, [
            'exclude_master_ids' => $masterIds,
            'exclude_mutasi_ids' => $mutasi->pluck('id')->all(),
            'saldo_override' => $saldoAfter,
        ]);

        if ($dryRun) {
            $this->printAuditReconciliation($summaryDbSekarang, $summaryRekapAkhirBuku, $summarySetelahRollback, $through);
        } else {
            $this->printSummaryComparison($summaryDbSekarang, $summarySetelahRollback, 'DB sebelum apply', 'Setelah rollback');
        }

        $this->info('Resync master (periode ≤ ' . $through . ') dari rekap — preview:');
        foreach ($remainingMasters as $m) {
            $calcEst = (float) DB::table('rekap_fee_sales')->where('fee_sales_id', $m->id)
                ->sum(DB::raw('total_revenue * ' . (float) $m->basis));
            $calcClaim = (float) DB::table('rekap_fee_sales')->where(['fee_sales_id' => $m->id, 'is_lunas' => 1])
                ->sum(DB::raw('total_revenue * ' . (float) $m->basis));
            if (abs($calcEst - (float) $m->estimated_fee) > 1 || abs($calcClaim - (float) $m->claimed_fee) > 1) {
                $this->line(sprintf(
                    '  %s id=%d est %s → %s | claimed %s → %s',
                    $m->period,
                    $m->id,
                    number_format($m->estimated_fee, 0, ',', '.'),
                    number_format($calcEst, 0, ',', '.'),
                    number_format($m->claimed_fee, 0, ',', '.'),
                    number_format($calcClaim, 0, ',', '.')
                ));
            }
        }

        if ($this->option('clear-target-months') && $target) {
            $this->warn('Target sales — kolom yang akan dikosongkan: ' . implode(', ', $targetCols));
        }

        if (!$apply) {
            $this->newLine();
            $this->comment('Tidak ada perubahan DB. Preview:');
            $this->line("  php artisan fee-sales:rollback-after-period --sales-id={$salesId} --through={$through} --dry-run --clear-target-months");
            $this->comment('Jalankan perubahan:');
            $this->line("  php artisan fee-sales:rollback-after-period --sales-id={$salesId} --through={$through} --apply --backup --clear-target-months");
            $this->comment('Pulihkan jika hasil tidak sesuai (pakai path file backup):');
            $this->line('  php artisan fee-sales:rollback-after-period --rollback=storage/app/fee_rollback_XXX.json --dry-run');
            $this->line('  php artisan fee-sales:rollback-after-period --rollback=storage/app/fee_rollback_XXX.json --apply');
            return 0;
        }

        $backupPath = storage_path('app/fee_rollback_' . $salesId . '_' . str_replace('-', '', $through) . '_' . date('Ymd_His') . '.json');
        $targetSnapshot = null;
        if ($this->option('clear-target-months') && $target) {
            $targetSnapshot = ['id' => $target->id];
            foreach ($targetCols as $col) {
                $targetSnapshot[$col] = $target->$col;
            }
        }

        $snapshot = [
            'version' => 1,
            'created_at' => Carbon::now()->toIso8601String(),
            'sales_id' => $salesId,
            'through' => $through,
            'masters_removed' => $masters->map(fn ($r) => (array) $r)->values()->all(),
            'rekap_removed' => DB::table('rekap_fee_sales')->whereIn('fee_sales_id', $masterIds)->get()->map(fn ($r) => (array) $r)->values()->all(),
            'mutasi_disabled' => $mutasi->map(fn ($r) => (array) $r)->values()->all(),
            'masters_before_resync' => $remainingMasters->map(fn ($r) => (array) $r)->values()->all(),
            'saldo_before' => $saldo ? (array) $saldo : null,
            'target_before' => $targetSnapshot,
        ];
        File::put($backupPath, json_encode($snapshot, JSON_PRETTY_PRINT));
        $this->info("Backup: {$backupPath}");

        DB::transaction(function () use ($masterIds, $mutasi, $remainingMasters, $target, $targetCols, $saldoAfter, $salesId) {
            if ($masterIds) {
                DB::table('rekap_fee_sales')->whereIn('fee_sales_id', $masterIds)->delete();
                DB::table('master_fee_sales')->whereIn('id', $masterIds)->delete();
            }

            foreach ($mutasi as $m) {
                DB::table('mutasi_fee_sales')->where('id', $m->id)->update(['is_active' => 0]);
            }

            foreach ($remainingMasters as $m) {
                $calcEst = (float) DB::table('rekap_fee_sales')->where('fee_sales_id', $m->id)
                    ->sum(DB::raw('total_revenue * ' . (float) $m->basis));
                $calcClaim = (float) DB::table('rekap_fee_sales')->where(['fee_sales_id' => $m->id, 'is_lunas' => 1])
                    ->sum(DB::raw('total_revenue * ' . (float) $m->basis));
                DB::table('master_fee_sales')->where('id', $m->id)->update([
                    'estimated_fee' => $calcEst,
                    'claimed_fee' => $calcClaim,
                    'updated_by' => 'RollbackFeeSales',
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::table('saldo_fee_sales')->where(['sales_id' => $salesId, 'is_active' => 1])
                ->orderByDesc('id')->limit(1)
                ->update([
                    'active_balance' => $saldoAfter,
                    'updated_by' => 'RollbackFeeSales',
                    'updated_at' => Carbon::now(),
                ]);

            if ($this->option('clear-target-months') && $target) {
                $update = ['updated_by' => 'RollbackFeeSales', 'updated_at' => Carbon::now()];
                foreach ($targetCols as $col) {
                    $update[$col] = null;
                }
                DB::table('master_target_sales')->where('id', $target->id)->update($update);
            }
        });

        $this->info('Rollback selesai.');
        $this->comment('Simpan path backup untuk restore jika perlu:');
        $this->line("  {$backupPath}");
        return 0;
    }

    private function restoreFromBackup(string $path, bool $dryRun): int
    {
        $path = $this->resolveBackupPath($path);
        if (!File::exists($path)) {
            $this->error("File tidak ditemukan: {$path}");
            return 1;
        }

        $data = json_decode(File::get($path), true);
        if (!$data) {
            $this->error('JSON tidak valid.');
            return 1;
        }

        $salesId = (int) ($data['sales_id'] ?? 0);
        $through = $data['through'] ?? '-';
        $mastersRemoved = collect($data['masters_removed'] ?? $data['masters'] ?? []);
        $rekapRemoved = collect($data['rekap_removed'] ?? $data['rekap'] ?? []);
        $mutasiDisabled = collect($data['mutasi_disabled'] ?? $data['mutasi'] ?? []);
        $mastersBefore = collect($data['masters_before_resync'] ?? []);
        $saldoBefore = $data['saldo_before'] ?? $data['saldo'] ?? null;
        $targetBefore = $data['target_before'] ?? null;

        $nama = DB::table('master_karyawan')->where('id', $salesId)->value('nama_lengkap') ?? '-';

        $this->info('');
        $this->info('========================================================================');
        $this->info('  RESTORE DARI BACKUP — ' . ($dryRun ? 'DRY-RUN' : 'APPLY'));
        $this->info("  File: {$path}");
        $this->info("  Sales: {$nama} (id={$salesId}), through: {$through}");
        $this->info('========================================================================');

        $this->line('Master yang akan dikembalikan (insert): ' . $mastersRemoved->count());
        foreach ($mastersRemoved as $m) {
            $this->line("  id={$m['id']} period={$m['period']}");
        }
        $this->line('Rekap yang akan dikembalikan: ' . $rekapRemoved->count());
        $this->line('Mutasi diaktifkan kembali: ' . $mutasiDisabled->count());
        $this->line('Master di-resync ulang (est/claimed): ' . $mastersBefore->count());
        if ($saldoBefore) {
            $this->line('Saldo → Rp ' . number_format((float) $saldoBefore['active_balance'], 2, ',', '.'));
        }
        if ($targetBefore) {
            $cols = array_diff(array_keys($targetBefore), ['id']);
            $this->line('Target sales id=' . $targetBefore['id'] . ' kolom: ' . implode(', ', $cols));
        }

        $summaryNow = $this->feeSummary($salesId);
        $summaryAfterRestore = $this->feeSummary($salesId, [
            'include_rekap_rows' => $rekapRemoved->all(),
            'include_mutasi_kredit_ids' => $mutasiDisabled->where('mutation_type', 'Kredit')->pluck('id')->filter()->all(),
            'saldo_override' => isset($saldoBefore['active_balance']) ? (float) $saldoBefore['active_balance'] : null,
        ]);
        $this->newLine();
        $this->printSummaryComparison($summaryNow, $summaryAfterRestore, 'KONDISI DB SEKARANG', 'SESUDAH RESTORE (simulasi)');

        if ($dryRun) {
            $this->newLine();
            $this->comment('Tidak ada perubahan DB. Jalankan restore:');
            $this->line("  php artisan fee-sales:rollback-after-period --rollback=\"{$path}\" --apply");
            return 0;
        }

        DB::transaction(function () use ($mastersRemoved, $rekapRemoved, $mutasiDisabled, $mastersBefore, $saldoBefore, $targetBefore, $salesId) {
            foreach ($mastersRemoved as $row) {
                $id = (int) $row['id'];
                if (DB::table('master_fee_sales')->where('id', $id)->exists()) {
                    DB::table('master_fee_sales')->where('id', $id)->update($row);
                } else {
                    DB::table('master_fee_sales')->insert($row);
                }
            }

            foreach ($rekapRemoved as $row) {
                $id = (int) $row['id'];
                if (!DB::table('rekap_fee_sales')->where('id', $id)->exists()) {
                    DB::table('rekap_fee_sales')->insert($row);
                }
            }

            foreach ($mutasiDisabled as $row) {
                DB::table('mutasi_fee_sales')->where('id', $row['id'])->update(['is_active' => 1]);
            }

            foreach ($mastersBefore as $row) {
                DB::table('master_fee_sales')->where('id', $row['id'])->update([
                    'estimated_fee' => $row['estimated_fee'],
                    'claimed_fee' => $row['claimed_fee'],
                    'updated_by' => 'RollbackRestore',
                    'updated_at' => Carbon::now(),
                ]);
            }

            if ($saldoBefore && isset($saldoBefore['id'])) {
                DB::table('saldo_fee_sales')->where('id', $saldoBefore['id'])->update([
                    'active_balance' => $saldoBefore['active_balance'],
                    'updated_by' => 'RollbackRestore',
                    'updated_at' => Carbon::now(),
                ]);
            }

            if ($targetBefore && isset($targetBefore['id'])) {
                $id = $targetBefore['id'];
                unset($targetBefore['id']);
                $update = $targetBefore;
                $update['updated_by'] = 'RollbackRestore';
                $update['updated_at'] = Carbon::now();
                DB::table('master_target_sales')->where('id', $id)->update($update);
            }
        });

        $this->info('Restore dari backup selesai.');
        return 0;
    }

    /**
     * Ringkasan selaras kartu UI Saldo Fee Sales (SaldoFeeSalesController).
     */
    private function feeSummary(int $salesId, array $options = []): array
    {
        $excludeMasterIds = $options['exclude_master_ids'] ?? [];
        $excludeMutasiIds = $options['exclude_mutasi_ids'] ?? [];
        $onlyPeriodLte = $options['only_period_lte'] ?? null;
        $includeRekapRows = collect($options['include_rekap_rows'] ?? []);
        $includeMutasiKreditIds = $options['include_mutasi_kredit_ids'] ?? [];

        $rekapPaid = (float) $this->rekapFeeQuery($salesId, $excludeMasterIds, 1, $onlyPeriodLte);
        $rekapUnpaid = (float) $this->rekapFeeQuery($salesId, $excludeMasterIds, 0, $onlyPeriodLte);

        foreach ($includeRekapRows as $row) {
            $basis = (float) ($row['basis'] ?? $this->masterBasis($row['fee_sales_id']));
            $fee = (float) $row['total_revenue'] * $basis;
            if (!empty($row['is_lunas'])) {
                $rekapPaid += $fee;
            } else {
                $rekapUnpaid += $fee;
            }
        }

        $rekapBruto = round($rekapPaid + $rekapUnpaid, 2);
        $menunggu = round($rekapUnpaid, 2);
        $rekapLunas = round($rekapPaid, 2);

        $kredit = (float) DB::table('mutasi_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true, 'mutation_type' => 'Kredit'])
            ->when($excludeMutasiIds, fn ($q) => $q->whereNotIn('id', $excludeMutasiIds))
            ->sum('amount');

        if ($includeMutasiKreditIds) {
            $extra = (float) DB::table('mutasi_fee_sales')
                ->whereIn('id', $includeMutasiKreditIds)
                ->where('mutation_type', 'Kredit')
                ->sum('amount');
            $kredit += $extra;
        }

        $dicairkan = (float) DB::table('withdrawal_fee_sales')
            ->where(['sales_id' => $salesId, 'is_active' => true])
            ->whereNull('rejected_by')
            ->sum('amount');

        $saldo = array_key_exists('saldo_override', $options) && $options['saldo_override'] !== null
            ? round((float) $options['saldo_override'], 2)
            : (float) (DB::table('saldo_fee_sales')->where(['sales_id' => $salesId, 'is_active' => 1])->orderByDesc('id')->value('active_balance') ?? 0);

        $feeDikredit = round($kredit, 2);
        $belumDikredit = round(max(0, $rekapLunas - $feeDikredit), 2);
        $totalFee = round($feeDikredit + $menunggu, 2);

        return [
            'saldo' => $saldo,
            'total_fee' => $totalFee,
            'fee_dikredit' => $feeDikredit,
            'fee_menunggu' => $menunggu,
            'fee_dicairkan' => round($dicairkan, 2),
            'fee_rekap_bruto' => $rekapBruto,
            'fee_lunas_belum_dikredit' => $belumDikredit,
        ];
    }

    private function rekapFeeQuery(int $salesId, array $excludeMasterIds, int $isLunas, ?string $onlyPeriodLte = null): float
    {
        return (float) DB::table('rekap_fee_sales as r')
            ->join('master_fee_sales as m', 'm.id', '=', 'r.fee_sales_id')
            ->where('m.sales_id', $salesId)
            ->where('m.is_active', true)
            ->where('r.is_lunas', $isLunas)
            ->when($excludeMasterIds, fn ($q) => $q->whereNotIn('m.id', $excludeMasterIds))
            ->when($onlyPeriodLte, fn ($q) => $q->where('m.period', '<=', $onlyPeriodLte))
            ->sum(DB::raw('r.total_revenue * m.basis'));
    }

    private function masterBasis(int $masterId): float
    {
        return (float) DB::table('master_fee_sales')->where('id', $masterId)->value('basis');
    }

    private function printAuditReconciliation(array $full, array $rekapAkhirBuku, array $rollback, string $through): void
    {
        $rows = [
            'Saldo (siap ditarik)' => 'saldo',
            'Total keseluruhan fee' => 'total_fee',
            'Fee sudah dikredit' => 'fee_dikredit',
            'Fee menunggu pelunasan' => 'fee_menunggu',
            'Fee sudah dicairkan' => 'fee_dicairkan',
        ];

        $h1 = 'DB sekarang';
        $h2 = 'Rekap ≤ ' . $through;
        $h3 = 'Setelah rollback';

        $tableRows = [];
        foreach ($rows as $label => $key) {
            $tableRows[] = [
                $label,
                $this->formatRp($full[$key] ?? 0),
                $this->formatRp($rekapAkhirBuku[$key] ?? 0),
                $this->formatRp($rollback[$key] ?? 0),
            ];
        }

        $this->newLine();
        $this->info('Ringkasan rekonsiliasi (selaras UI Saldo Fee Sales):');
        $this->table(['Metrik', $h1, $h2, $h3], $tableRows);

        $this->newLine();
        $this->comment('Kolom 1: data DB apa adanya (bisa termasuk master Okt).');
        $this->comment('Kolom 2: rekap fee hanya master periode ≤ ' . $through . ', mutasi/saldo belum dibersihkan.');
        $this->comment('Kolom 3: simulasi akhir ' . $through . ' setelah hapus Okt+, mutasi terkait dinonaktifkan, resync master & saldo.');
        $this->comment('Total = dikredit + menunggu (bukan rekap bruto).');

        foreach ([$h1 => $full, $h2 => $rekapAkhirBuku, $h3 => $rollback] as $lbl => $row) {
            if (!empty($row['fee_lunas_belum_dikredit']) && $row['fee_lunas_belum_dikredit'] >= 1) {
                $this->warn("  {$lbl}: fee lunas rekap belum dikredit " . $this->formatRp($row['fee_lunas_belum_dikredit'])
                    . ' (rekap bruto ' . $this->formatRp($row['fee_rekap_bruto'] ?? 0) . ')');
            }
        }
    }

    private function printSummaryComparison(array $before, array $after, string $labelBefore = 'SEBELUM', string $labelAfter = 'SESUDAH'): void
    {
        $rows = [
            'Saldo (siap ditarik)' => 'saldo',
            'Total keseluruhan fee' => 'total_fee',
            'Fee sudah dikredit' => 'fee_dikredit',
            'Fee menunggu pelunasan' => 'fee_menunggu',
            'Fee sudah dicairkan' => 'fee_dicairkan',
        ];

        $tableRows = [];
        foreach ($rows as $label => $key) {
            $b = (float) ($before[$key] ?? 0);
            $a = (float) ($after[$key] ?? 0);
            $d = round($a - $b, 2);
            $sign = $d > 0 ? '+' : '';
            $tableRows[] = [
                $label,
                $this->formatRp($b),
                $this->formatRp($a),
                $sign . $this->formatRp($d),
            ];
        }

        $this->newLine();
        $this->info('Ringkasan (seperti UI Saldo Fee Sales):');
        $this->table(['Metrik', $labelBefore, $labelAfter, 'Δ'], $tableRows);

        $this->newLine();
        $this->comment('Total keseluruhan = fee dikredit + fee menunggu pelunasan (angka yang dapat dipertanggungjawabkan).');
        foreach ([$labelBefore => $before, $labelAfter => $after] as $lbl => $row) {
            $cek = round($row['fee_dikredit'] + $row['fee_menunggu'], 2);
            $this->line('  ' . $lbl . ': ' . $this->formatRp($row['fee_dikredit']) . ' + ' . $this->formatRp($row['fee_menunggu']) . ' = ' . $this->formatRp($cek)
                . ' (total ditampilkan: ' . $this->formatRp($row['total_fee']) . ')');
            if (!empty($row['fee_lunas_belum_dikredit']) && $row['fee_lunas_belum_dikredit'] >= 1) {
                $this->warn('    ⚠ Fee lunas di rekap belum masuk mutasi (perlu perbaikan data): ' . $this->formatRp($row['fee_lunas_belum_dikredit'])
                    . ' | rekap bruto: ' . $this->formatRp($row['fee_rekap_bruto'] ?? 0));
            }
        }
    }

    private function formatRp(float $value): string
    {
        return 'Rp ' . number_format($value, 0, ',', '.');
    }

    private function resolveBackupPath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (File::exists($path)) {
            return $path;
        }
        $fromStorage = storage_path('app/' . ltrim(str_replace('storage/app/', '', $path), '/\\'));
        if (File::exists($fromStorage)) {
            return $fromStorage;
        }
        return $path;
    }

    private function periodGreater(string $period, string $through): bool
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $period > $through;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $period, $m)) {
            $normalized = sprintf('%04d-%02d', (int) $m[1], (int) $m[2]);
            return $normalized > $through;
        }
        return false;
    }

    private function monthLabelsAfter(string $through): array
    {
        [, $mm] = explode('-', $through);
        $labels = [];
        for ($i = (int) $mm + 1; $i <= 12; $i++) {
            $key = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $labels[] = self::INDO_MONTH_LABELS[$key];
        }
        return $labels;
    }

    private function targetColumnsAfter(string $through): array
    {
        [, $mm] = explode('-', $through);
        $cols = [];
        for ($i = (int) $mm + 1; $i <= 12; $i++) {
            $cols[] = self::INDO_MONTHS[str_pad((string) $i, 2, '0', STR_PAD_LEFT)];
        }
        return $cols;
    }
}
