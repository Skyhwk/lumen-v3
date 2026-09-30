<?php

namespace App\Console\Commands;

use App\Models\QuotationNonKontrak;
use App\Services\Quotation\ReactivateNeverOrderedQuotationService;
use Illuminate\Console\Command;

class ReactivateNeverOrderedQuotationCommand extends Command
{
    protected $signature = 'quotation:reactivate-never-ordered
        {--months= : Step1 copy: bulan QT sumber. Contoh: 7,8,9}
        {--year= : Step1 copy: tahun QT sumber}
        {--created-by=SYSTEM_REACTIVATE : Nama created_by / emailed_by}
        {--limit= : Step1 — batasi jumlah copy+render}
        {--skip-render : Step1 — copy saja, tanpa render PDF}
        {--sync-render : Step1 — render sync (no queue). Default: env REACTIVATE_RENDER_SYNC}
        {--send-email : Step2 — kirim email dari log type=new (terpisah dari copy)}
        {--email-to= : Step2 — override tujuan (aman). Default: env REACTIVATE_EMAIL_OVERRIDE}
        {--to-pic : Step2 BAHAYA — kirim ke PIC asli (default OFF)}
        {--resend : Step2 — kirim ulang meski sudah is_emailed}
        {--dry-run : Simulasi (copy/email/rollback) tanpa ubah DB / kirim email}
        {--rollback : Nonaktifkan QT hasil copy + hapus log type=new}
        {--scope= : Step2/rollback: all|months|date|range|ids|docs (kosong = interaktif)}
        {--log-ids= : Spesifik ID log (comma)}
        {--date= : Satu tanggal log (Y-m-d)}
        {--since= : Dari tanggal (Y-m-d)}
        {--until= : Sampai tanggal (Y-m-d)}
        {--no-qt= : By no_document sumber (comma)}
        {--no-qt-new= : By no_document hasil copy (comma)}
        {--force : Skip konfirmasi interaktif}';

    protected $description = 'Step1: copy+render QT never-ordered | Step2: --send-email (log type=new, safe override) | --dry-run';

    public function handle()
    {
        $this->printBanner();
        $service = new ReactivateNeverOrderedQuotationService();

        if ($this->option('rollback')) {
            return $this->runRollback($service);
        }

        if ($this->option('send-email')) {
            return $this->runSendEmail($service);
        }

        // Default: copy + render (satu flow, tanpa email)
        return $this->runCopy($service);
    }

    private function runCopy(ReactivateNeverOrderedQuotationService $service)
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipRender = (bool) $this->option('skip-render');
        $syncRender = $this->option('sync-render')
            ? true
            : filter_var(env('REACTIVATE_RENDER_SYNC', false), FILTER_VALIDATE_BOOLEAN);
        $createdBy = (string) ($this->option('created-by') ?: 'SYSTEM_REACTIVATE');
        $limit = $this->option('limit') !== null && $this->option('limit') !== ''
            ? (int) $this->option('limit')
            : null;

        $renderMode = $skipRender ? 'SKIP' : ($syncRender ? 'SYNC (langsung)' : 'QUEUE job');

        $this->step(1, 'Resolve periode bulan/tahun');
        list($months, $year) = $this->resolveMonthsAndYear();
        $this->info(sprintf(
            '  Periode: %s/%d | dry-run=%s | limit=%s | render=%s | created_by=%s',
            implode(',', $months),
            $year,
            $dryRun ? 'YES' : 'NO',
            $limit ? $limit : 'all',
            $renderMode,
            $createdBy
        ));

        $this->step(2, 'Checklist prerequisite');
        $checks = [
            ['Model QuotationNonKontrak', class_exists(QuotationNonKontrak::class)],
            ['Service copy terpisah', class_exists(ReactivateNeverOrderedQuotationService::class)],
            ['Bulan valid (1-12)', $this->monthsValid($months)],
        ];
        $this->renderChecklist($checks);
        foreach ($checks as $check) {
            if (!$check[1]) {
                $this->error('Checklist gagal: ' . $check[0]);
                return 1;
            }
        }
        if ($dryRun) {
            $this->line('  [INFO] Tabel qt_exist_reactivation_logs tidak dicek di dry-run (dibuat pihak lain / DBA).');
        }

        $this->step(3, 'Hitung kandidat penuh (draft/emailed/sp/NULL, 1 QT terakhir/pelanggan, belum order)');
        $allCandidates = $service->findCandidates($months, $year, null);
        $total = $allCandidates->count();
        $candidates = ($limit !== null && $limit > 0)
            ? $allCandidates->take($limit)->values()
            : $allCandidates;
        $this->info("  Total kandidat periode : {$total}");
        $this->info('  Akan diproses sekarang: ' . $candidates->count() . ($limit ? " (--limit={$limit})" : ''));

        if ($candidates->isEmpty()) {
            $this->warn('Tidak ada kandidat. Selesai.');
            return 0;
        }

        $preview = [];
        foreach ($candidates->take(20) as $row) {
            $preview[] = [
                $row->id,
                $row->no_document,
                $row->flag_status === null ? 'NULL' : $row->flag_status,
                $row->pelanggan_ID,
                $row->id_cabang,
                (string) $row->created_at,
            ];
        }
        $this->table(['id', 'no_document', 'flag', 'pelanggan_ID', 'cabang', 'created_at'], $preview);
        if ($candidates->count() > 20) {
            $this->line('  ... dan ' . ($candidates->count() - 20) . ' baris lainnya di batch ini');
        }
        if ($total > $candidates->count()) {
            $this->line('  Sisa belum diproses: ' . ($total - $candidates->count()) . ' (jalankan ulang; yang sudah di-log akan diskip)');
        }

        $this->step(4, 'Konfirmasi eksekusi');
        if ($dryRun) {
            $this->warn('  DRY-RUN: tidak ada insert QT / log / render.');
        } elseif (!$this->option('force') && !$this->confirm(
            'Lanjut copy ' . $candidates->count() . ' dari ' . $total . ' kandidat'
            . ($skipRender ? ' (tanpa render)' : ' + render ' . $renderMode) . '?',
            false
        )) {
            $this->warn('Dibatalkan user.');
            return 0;
        }

        $this->step(5, $dryRun
            ? 'Simulasi copy'
            : ('Copy QT + log' . ($skipRender ? '' : ' + render ' . $renderMode)));
        $ok = 0;
        $fail = 0;
        $rows = [];

        $bar = $this->output->createProgressBar($candidates->count());
        $bar->start();

        foreach ($candidates as $row) {
            if ($dryRun) {
                $ok++;
                $rows[] = [
                    $row->no_document,
                    '(dry-run)',
                    $row->pelanggan_ID,
                    'SKIP_WRITE',
                    $skipRender ? 'SKIP' : ($syncRender ? 'WOULD_SYNC' : 'WOULD_QUEUE'),
                ];
                $bar->advance();
                continue;
            }

            $source = QuotationNonKontrak::find($row->id);
            if (!$source) {
                $fail++;
                $rows[] = [$row->no_document, '-', $row->pelanggan_ID, 'SOURCE_MISSING', '-'];
                $bar->advance();
                continue;
            }

            $result = $service->copyOne($source, $createdBy, !$skipRender, $syncRender);
            if ($result['ok']) {
                $ok++;
                $rows[] = [
                    $result['source']->no_document,
                    $result['copy']->no_document,
                    $result['source']->pelanggan_ID,
                    'OK',
                    $result['render'] ?? '-',
                ];
            } else {
                $fail++;
                $rows[] = [$row->no_document, '-', $row->pelanggan_ID, $result['message'], '-'];
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->step(6, 'Ringkasan tracking');
        $this->table(
            ['no_qt (sumber)', 'no_qt_new', 'id_pelanggan', 'status', 'render'],
            array_slice($rows, 0, 50)
        );
        if (count($rows) > 50) {
            $this->line('  ... ' . (count($rows) - 50) . ' baris tracking lainnya (lihat log quotation)');
        }

        $this->info("Sukses: {$ok} | Gagal: {$fail} | Mode: " . ($dryRun ? 'dry-run' : 'execute'));
        $this->line('Log file: storage/logs/create_quotation/quotation.log');
        $this->line('DB log  : qt_exist_reactivation_logs (type=new)');
        if (!$dryRun && !$skipRender) {
            $this->line('Render  : ' . $renderMode);
        }

        return $fail > 0 ? 1 : 0;
    }

    private function runSendEmail(ReactivateNeverOrderedQuotationService $service)
    {
        $dryRun = (bool) $this->option('dry-run');
        $resend = (bool) $this->option('resend');
        $allowPic = (bool) $this->option('to-pic');
        $karyawan = (string) ($this->option('created-by') ?: 'SYSTEM_REACTIVATE');
        $emailTo = trim((string) ($this->option('email-to') ?: env('REACTIVATE_EMAIL_OVERRIDE', '')));

        $this->step(1, 'Mode SEND-EMAIL — log type=new saja (terpisah dari copy)');
        $this->info('  dry-run=' . ($dryRun ? 'YES' : 'NO'));
        $filters = $this->resolveRollbackFilters();
        $this->info('  Filter: ' . $this->describeRollbackFilters($filters));
        $this->info('  Karyawan/emailed_by: ' . $karyawan);
        $this->info('  Skip already emailed: ' . ($resend ? 'NO (--resend)' : 'YES'));

        if ($allowPic) {
            $this->error('  ⚠ --to-pic AKTIF: email akan ke email_pic_order asli (BAHAYA)');
        } else {
            if ($emailTo === '' || !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
                $this->error('Override email wajib. Set --email-to=anda@intilab.com atau REACTIVATE_EMAIL_OVERRIDE di .env');
                $this->line('  (Default aman: TIDAK kirim ke PIC)');
                return 1;
            }
            $this->warn('  SAFE MODE: semua email → ' . $emailTo . ' (bukan PIC)');
        }

        $logs = $service->getLogs($filters);
        $this->info('  Log kandidat email: ' . $logs->count());
        if ($logs->isEmpty()) {
            $this->warn('Tidak ada log cocok filter.');
            return 0;
        }

        $preview = [];
        foreach ($logs->take(30) as $l) {
            $qt = QuotationNonKontrak::where('no_document', $l->no_qt_new)->first();
            $pic = $qt ? ($qt->email_pic_order ?: '-') : 'MISSING';
            $preview[] = [
                $l->id,
                $l->no_qt_new,
                $pic,
                $allowPic ? $pic : $emailTo,
                $qt ? ((int) $qt->is_emailed ? 'emailed' : ($qt->flag_status ?: '-')) : '-',
            ];
        }
        $this->table(['log_id', 'no_qt_new', 'pic', 'will_send_to', 'status'], $preview);
        if ($logs->count() > 30) {
            $this->line('  ... dan ' . ($logs->count() - 30) . ' log lainnya');
        }

        if ($dryRun) {
            $this->warn('  DRY-RUN: tidak kirim email / update flag.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm(
            'Kirim email ke ' . $logs->count() . ' QT'
            . ($allowPic ? ' (KE PIC ASLI!)' : ' (safe → ' . $emailTo . ')') . '?',
            false
        )) {
            $this->warn('Dibatalkan user.');
            return 0;
        }

        $this->step(2, 'Kirim email + update flag_status');
        $result = $service->sendEmailsFromLogs(
            $filters,
            $karyawan,
            !$resend,
            $allowPic ? null : $emailTo,
            $allowPic
        );

        $this->table(
            ['no_qt_new', 'to', 'status', 'message'],
            array_slice($result['rows'], 0, 50)
        );
        if (count($result['rows']) > 50) {
            $this->line('  ... ' . (count($result['rows']) - 50) . ' baris lainnya');
        }

        $this->info("Matched: {$result['matched']} | Sent: {$result['sent']} | Failed: {$result['failed']} | Skip: " . count($result['skipped']));
        $this->line('Log file: storage/logs/create_quotation/quotation.log');

        return $result['failed'] > 0 ? 1 : 0;
    }

    private function runRollback(ReactivateNeverOrderedQuotationService $service)
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->step(1, 'Mode ROLLBACK — nonaktifkan QT baru + hapus log type=new');

        $filters = $this->resolveRollbackFilters();
        $this->info('  Filter: ' . $this->describeRollbackFilters($filters));

        $logs = $service->getLogs($filters);
        $this->info('  Log kandidat rollback: ' . $logs->count());
        if ($logs->isEmpty()) {
            $this->warn('Tidak ada log cocok filter (atau tabel qt_exist_reactivation_logs belum ada).');
            return 0;
        }

        $preview = [];
        foreach ($logs->take(30) as $l) {
            $preview[] = [
                $l->id,
                $l->id_pelanggan,
                $l->no_qt,
                $l->no_qt_new,
                (string) $l->created_at,
            ];
        }
        $this->table(['log_id', 'id_pelanggan', 'no_qt', 'no_qt_new', 'created_at'], $preview);
        if ($logs->count() > 30) {
            $this->line('  ... dan ' . ($logs->count() - 30) . ' log lainnya');
        }

        if ($dryRun) {
            $this->warn('DRY-RUN rollback: tidak mengubah data.');
            return 0;
        }

        if (!$this->option('force') && !$this->confirm('Yakin rollback ' . $logs->count() . ' log?', false)) {
            $this->warn('Dibatalkan.');
            return 0;
        }

        $this->step(2, 'Eksekusi rollback');
        $result = $service->rollback($filters);
        $this->info('Matched         : ' . $result['matched']);
        $this->info('QT dinonaktifkan: ' . $result['deactivated']);
        $this->info('Log dihapus     : ' . $result['logs_deleted']);
        if (!empty($result['skipped'])) {
            $this->warn('Skipped:');
            foreach ($result['skipped'] as $msg) {
                $this->line('  - ' . $msg);
            }
        }

        return 0;
    }

    /**
     * Scope rollback:
     * - all
     * - months (+ year)
     * - date (satu hari)
     * - range (since/until)
     * - ids (log-ids)
     * - docs (no-qt / no-qt-new)
     */
    private function resolveRollbackFilters()
    {
        $filters = [];

        // Preferensi opsi eksplisit dulu (bisa kombinasi)
        if ($this->option('log-ids')) {
            $filters['log_ids'] = array_values(array_filter(array_map(
                'intval',
                preg_split('/[,\s]+/', (string) $this->option('log-ids'))
            )));
        }
        if ($this->option('no-qt')) {
            $filters['no_qt'] = $this->splitCsv($this->option('no-qt'));
        }
        if ($this->option('no-qt-new')) {
            $filters['no_qt_new'] = $this->splitCsv($this->option('no-qt-new'));
        }
        if ($this->option('date')) {
            $filters['date'] = $this->option('date');
        }
        if ($this->option('since')) {
            $filters['since'] = $this->option('since');
        }
        if ($this->option('until')) {
            $filters['until'] = $this->option('until');
        }
        if ($this->option('months')) {
            $filters['months'] = array_values(array_filter(array_map(
                'intval',
                preg_split('/[,\s]+/', (string) $this->option('months'))
            )));
            $year = $this->option('year');
            if ($year === null || $year === '') {
                $year = $this->ask('Tahun log untuk filter bulan', (string) date('Y'));
            }
            $filters['year'] = (int) $year;
        }

        $hasExplicit = !empty($filters);
        $scope = strtolower(trim((string) $this->option('scope')));

        if ($hasExplicit && $scope === '') {
            return $filters;
        }

        if ($scope === '') {
            $this->line('Pilih scope rollback:');
            $this->line('  1) all      — seluruh log type=new');
            $this->line('  2) months   — per bulan created_at log');
            $this->line('  3) date     — satu tanggal');
            $this->line('  4) range    — rentang since..until');
            $this->line('  5) ids      — spesifik log id');
            $this->line('  6) docs     — spesifik no_qt / no_qt_new');
            $choice = $this->ask('Scope (1-6 / all|months|date|range|ids|docs)', 'date');
            $map = [
                '1' => 'all',
                '2' => 'months',
                '3' => 'date',
                '4' => 'range',
                '5' => 'ids',
                '6' => 'docs',
            ];
            $scope = isset($map[$choice]) ? $map[$choice] : strtolower($choice);
        }

        switch ($scope) {
            case 'all':
                $filters['all'] = true;
                break;

            case 'months':
                if (empty($filters['months'])) {
                    list($months, $year) = $this->resolveMonthsAndYear();
                    $filters['months'] = $months;
                    $filters['year'] = $year;
                }
                break;

            case 'date':
                if (empty($filters['date'])) {
                    $filters['date'] = $this->ask('Tanggal log (Y-m-d)', date('Y-m-d'));
                }
                // jangan campur since/until saat date tunggal
                unset($filters['since'], $filters['until'], $filters['months'], $filters['year']);
                break;

            case 'range':
                if (empty($filters['since'])) {
                    $filters['since'] = $this->ask('Since (Y-m-d)', date('Y-m-01'));
                }
                if (empty($filters['until'])) {
                    $filters['until'] = $this->ask('Until (Y-m-d)', date('Y-m-d'));
                }
                unset($filters['date'], $filters['months'], $filters['year']);
                break;

            case 'ids':
                if (empty($filters['log_ids'])) {
                    $raw = $this->ask('Log IDs (comma)', '');
                    $filters['log_ids'] = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', $raw))));
                }
                break;

            case 'docs':
                if (empty($filters['no_qt']) && empty($filters['no_qt_new'])) {
                    $mode = $this->ask('Filter dokumen: source|new|both', 'new');
                    if ($mode === 'source' || $mode === 'both') {
                        $filters['no_qt'] = $this->splitCsv($this->ask('no_qt sumber (comma)', ''));
                    }
                    if ($mode === 'new' || $mode === 'both') {
                        $filters['no_qt_new'] = $this->splitCsv($this->ask('no_qt_new (comma)', ''));
                    }
                }
                break;

            default:
                $this->warn("Scope '{$scope}' tidak dikenali — fallback all.");
                $filters['all'] = true;
                break;
        }

        return $filters;
    }

    private function describeRollbackFilters(array $filters)
    {
        $parts = [];
        if (!empty($filters['all']) && count($filters) === 1) {
            return 'ALL type=new';
        }
        if (!empty($filters['log_ids'])) {
            $parts[] = 'ids=' . implode(',', $filters['log_ids']);
        }
        if (!empty($filters['date'])) {
            $parts[] = 'date=' . $filters['date'];
        }
        if (!empty($filters['since'])) {
            $parts[] = 'since=' . (string) $filters['since'];
        }
        if (!empty($filters['until'])) {
            $parts[] = 'until=' . (string) $filters['until'];
        }
        if (!empty($filters['months'])) {
            $parts[] = 'months=' . implode(',', $filters['months']) . '/' . ($filters['year'] ?? '?');
        }
        if (!empty($filters['no_qt'])) {
            $parts[] = 'no_qt=' . implode(',', $filters['no_qt']);
        }
        if (!empty($filters['no_qt_new'])) {
            $parts[] = 'no_qt_new=' . implode(',', $filters['no_qt_new']);
        }
        if (!empty($filters['all'])) {
            $parts[] = 'all';
        }

        return empty($parts) ? '(tidak ada filter — berbahaya)' : implode(' | ', $parts);
    }

    private function splitCsv($value)
    {
        return array_values(array_filter(array_map('trim', preg_split('/,/', (string) $value))));
    }

    private function resolveMonthsAndYear()
    {
        $monthsRaw = $this->option('months');
        if ($monthsRaw === null || $monthsRaw === '') {
            $monthsRaw = $this->ask(
                'Masukkan bulan (1-12, pisah koma). Contoh Jul-Sep: 7,8,9',
                '7,8,9'
            );
        }

        $yearRaw = $this->option('year');
        if ($yearRaw === null || $yearRaw === '') {
            $yearRaw = $this->ask('Masukkan tahun', (string) date('Y'));
        }

        $months = array_values(array_filter(array_map('intval', preg_split('/[,\s]+/', (string) $monthsRaw))));
        $year = (int) $yearRaw;

        return [$months, $year];
    }

    private function monthsValid(array $months)
    {
        if (empty($months)) {
            return false;
        }
        foreach ($months as $m) {
            if ($m < 1 || $m > 12) {
                return false;
            }
        }
        return true;
    }

    private function renderChecklist(array $checks)
    {
        foreach ($checks as $check) {
            $label = $check[0];
            $ok = $check[1];
            $mark = $ok ? '[OK]' : '[FAIL]';
            if ($ok) {
                $this->info("  {$mark} {$label}");
            } else {
                $this->error("  {$mark} {$label}");
            }
        }
    }

    private function step($n, $title)
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>STEP {$n}</> — {$title}");
    }

    private function printBanner()
    {
        $this->newLine();
        $this->line('==============================================');
        $this->line(' QT Reactivate Never-Ordered (Non Kontrak)');
        $this->line(' Step1 default : copy + render (log type=new)');
        $this->line(' Step2         : --send-email (terpisah, aman)');
        $this->line(' Simulasi      : --dry-run');
        $this->line('==============================================');
    }
}
