<?php

namespace App\Console\Commands;

use App\Services\LhpBackfillService;
use Illuminate\Console\Command;

class LhpRefreshIsokinetikCustomCommand extends Command
{
    protected $signature = 'lhp:refresh-isokinetik-custom
        {--from= : Filter tanggal_lhp mulai tanggal ini}
        {--to= : Filter tanggal_lhp sebelum tanggal ini}
        {--month= : Filter tanggal_lhp dalam bulan tertentu, format YYYY-MM}
        {--no-lhp= : Filter satu no LHP/CFR}
        {--no-sampel= : Filter header yang mengandung no sampel ini}
        {--limit= : Batasi jumlah header yang diproses}
        {--progress-every=25 : Tampilkan progress setiap N header, isi 0 untuk matikan}
        {--created-by=System : Hanya update header dengan created_by ini}
        {--connection=mysql : Koneksi target/source}
        {--dry-run : Cek kandidat tanpa update custom}';

    protected $description = 'Refresh lhps_emisi_isokinetik_custom untuk LHP isokinetik existing yang dibuat System';

    public function handle()
    {
        $filters = [
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'month' => $this->option('month'),
            'no_lhp' => $this->option('no-lhp'),
            'no_sampel' => $this->option('no-sampel'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
            'progress_every' => (int) $this->option('progress-every'),
            'created_by' => $this->option('created-by') ?: 'System',
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $this->info('===== Start Refresh Isokinetik Custom =====');
        $this->info('Connection : ' . $this->option('connection'));
        $this->info('Created By : ' . $filters['created_by']);
        $this->info('From       : ' . ($filters['from'] ?: '-'));
        $this->info('To         : ' . ($filters['to'] ?: '-'));
        $this->info('Month      : ' . ($filters['month'] ?: '-'));
        $this->info('No LHP     : ' . ($filters['no_lhp'] ?: '-'));
        $this->info('No Sampel  : ' . ($filters['no_sampel'] ?: '-'));
        $this->info('Dry run    : ' . ($filters['dry_run'] ? 'YES' : 'NO'));
        $this->info('Limit      : ' . ($filters['limit'] ?: '-'));
        $this->info('Progress   : every ' . ($filters['progress_every'] > 0 ? $filters['progress_every'] : 'OFF') . ' header');

        if (!$filters['dry_run'] && !$filters['no_lhp'] && !$filters['no_sampel']) {
            $this->warn('Menjalankan update custom isokinetik tanpa filter spesifik. Disarankan test dulu pakai --dry-run atau filter --no-lhp/--no-sampel.');
        }

        $filters['progress_callback'] = function (array $progress) {
            $this->line(sprintf(
                'PROGRESS EMISI_ISOKINETIK %d/%d | updated=%d skipped=%d failed=%d | current=%s',
                $progress['processed'],
                $progress['scanned'],
                $progress['updated'],
                $progress['skipped'],
                $progress['failed'],
                $progress['current']
            ));
        };

        $filters['failure_callback'] = function ($message) {
            $this->error($message);
        };

        try {
            $service = new LhpBackfillService($this->option('connection'));
            $result = $service->refreshIsokinetikCustom($filters);
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
            return 1;
        }

        $this->line('');
        $this->info('EMISI_ISOKINETIK');
        $this->line('Scanned        : ' . $result['scanned']);
        $this->line('Updated        : ' . $result['updated']);
        $this->line('Deleted Custom : ' . $result['deleted_custom']);
        $this->line('Inserted Custom: ' . $result['inserted_custom']);
        $this->line('Skipped        : ' . $result['skipped']);
        $this->line('Failed         : ' . $result['failed']);

        foreach (array_slice($result['messages'], 0, 20) as $message) {
            $this->line('- ' . $message);
        }
        if (count($result['messages']) > 20) {
            $this->line('- ... ' . (count($result['messages']) - 20) . ' pesan lain disembunyikan');
        }

        $this->info('===== Finish Refresh Isokinetik Custom =====');
        return $result['failed'] > 0 ? 1 : 0;
    }
}