<?php

namespace App\Console\Commands;

use App\Services\LhpBackfillService;
use Illuminate\Console\Command;

class LhpRefreshLingHeaderCommand extends Command
{
    protected $signature = 'lhp:refresh-ling-header
        {--type=all : Tipe lingkungan, all/ling/udara_lingkungan_hidup/udara_lingkungan_kerja}
        {--from= : Filter tanggal_lhp mulai tanggal ini}
        {--to= : Filter tanggal_lhp sebelum tanggal ini}
        {--month= : Filter tanggal_lhp dalam bulan tertentu, format YYYY-MM}
        {--before= : Filter tanggal_lhp sebelum tanggal ini jika from/to/month kosong}
        {--no-lhp= : Filter satu no LHP/CFR}
        {--no-sampel= : Filter header yang mengandung no sampel ini}
        {--limit= : Batasi jumlah header yang diproses}
        {--progress-every=25 : Tampilkan progress setiap N header, isi 0 untuk matikan}
        {--created-by=System : Hanya update header dengan created_by ini}
        {--connection=mysql : Koneksi target LHP/header dan sumber detail/lapangan}
        {--dry-run : Cek kandidat tanpa update header}';

    protected $description = 'Refresh header LHP ULH/ULK existing yang dibuat oleh System dari detail/data lapangan lingkungan';

    public function handle()
    {
        $filters = [
            'type' => $this->option('type'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'month' => $this->option('month'),
            'before' => $this->option('before'),
            'no_lhp' => $this->option('no-lhp'),
            'no_sampel' => $this->option('no-sampel'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
            'progress_every' => (int) $this->option('progress-every'),
            'created_by' => $this->option('created-by') ?: 'System',
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $this->info('===== Start Refresh Ling Header =====');
        $this->info('Connection : ' . $this->option('connection'));
        $this->info('Type       : ' . $filters['type']);
        $this->info('Created By : ' . $filters['created_by']);
        $this->info('Before     : ' . ($filters['before'] ?: '-'));
        $this->info('From       : ' . ($filters['from'] ?: '-'));
        $this->info('To         : ' . ($filters['to'] ?: '-'));
        $this->info('Month      : ' . ($filters['month'] ?: '-'));
        $this->info('No LHP     : ' . ($filters['no_lhp'] ?: '-'));
        $this->info('No Sampel  : ' . ($filters['no_sampel'] ?: '-'));
        $this->info('Dry run    : ' . ($filters['dry_run'] ? 'YES' : 'NO'));
        $this->info('Limit      : ' . ($filters['limit'] ?: '-'));
        $this->info('Progress   : every ' . ($filters['progress_every'] > 0 ? $filters['progress_every'] : 'OFF') . ' header');

        if (!$filters['dry_run'] && !$filters['no_lhp'] && !$filters['no_sampel']) {
            $this->warn('Menjalankan update header lingkungan tanpa filter spesifik. Disarankan test dulu pakai --dry-run atau filter --no-lhp/--no-sampel.');
        }

        $filters['progress_callback'] = function (array $progress) {
            $this->line(sprintf(
                'PROGRESS %s %d/%d | updated=%d skipped=%d failed=%d | current=%s',
                strtoupper($progress['type']),
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
            $summary = $service->refreshLingHeaders($filters);
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
            return 1;
        }

        foreach ($summary['types'] as $typeName => $result) {
            $this->line('');
            $this->info(strtoupper($typeName));
            $this->line('Scanned : ' . $result['scanned']);
            $this->line('Updated : ' . $result['updated']);
            $this->line('Skipped : ' . $result['skipped']);
            $this->line('Failed  : ' . $result['failed']);

            foreach (array_slice($result['messages'], 0, 20) as $message) {
                $this->line('- ' . $message);
            }

            if (count($result['messages']) > 20) {
                $this->line('- ... ' . (count($result['messages']) - 20) . ' pesan lain disembunyikan');
            }
        }

        $this->line('');
        $this->info('TOTAL');
        $this->line('Scanned : ' . $summary['total']['scanned']);
        $this->line('Updated : ' . $summary['total']['updated']);
        $this->line('Skipped : ' . $summary['total']['skipped']);
        $this->line('Failed  : ' . $summary['total']['failed']);
        $this->info('===== Finish Refresh Ling Header =====');

        return $summary['total']['failed'] > 0 ? 1 : 0;
    }
}