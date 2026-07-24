<?php

namespace App\Console\Commands;

use App\Services\LhpBackfillService;
use Illuminate\Console\Command;

class LhpRefreshKpgiDetailCommand extends Command
{
    protected $signature = 'lhp:refresh-kpgi-detail
        {--type=all : Tipe KPGI, all/kpgi/kebisingan/pencahayaan/getaran_personel/iklim_kerja}
        {--from= : Ambil data mulai tanggal ini}
        {--to= : Ambil data sebelum tanggal ini}
        {--month= : Ambil data dalam bulan tertentu, format YYYY-MM}
        {--before=2026-01-01 : Ambil data sebelum tanggal ini jika from/to/month kosong}
        {--cfr= : Filter satu CFR/no LHP}
        {--no-sampel= : Filter satu no sampel}
        {--limit= : Batasi jumlah group CFR yang diproses}
        {--progress-every=25 : Tampilkan progress setiap N group, isi 0 untuk matikan}
        {--created-by=System : Hanya update header dengan created_by ini}
        {--connection=mysql : Koneksi target untuk header/detail LHP}
        {--order-connection= : Koneksi source khusus order_detail dan order_header}
        {--dry-run : Cek kandidat tanpa update detail}';

    protected $description = 'Refresh detail LHP KPGI existing yang dibuat oleh System tanpa membuat header baru';

    public function handle()
    {
        $filters = [
            'type' => $this->option('type'),
            'from' => $this->option('from'),
            'to' => $this->option('to'),
            'month' => $this->option('month'),
            'before' => $this->option('before'),
            'cfr' => $this->option('cfr'),
            'no_sampel' => $this->option('no-sampel'),
            'limit' => $this->option('limit') ? (int) $this->option('limit') : null,
            'progress_every' => (int) $this->option('progress-every'),
            'created_by' => $this->option('created-by') ?: 'System',
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $this->info('===== Start Refresh KPGI Detail =====');
        $this->info('Connection : ' . $this->option('connection'));
        $this->info('Order Conn : ' . ($this->option('order-connection') ?: $this->option('connection')));
        $this->info('Type       : ' . $filters['type']);
        $this->info('Created By : ' . $filters['created_by']);
        $this->info('Before     : ' . ($filters['before'] ?: '-'));
        $this->info('From       : ' . ($filters['from'] ?: '-'));
        $this->info('To         : ' . ($filters['to'] ?: '-'));
        $this->info('Month      : ' . ($filters['month'] ?: '-'));
        $this->info('CFR        : ' . ($filters['cfr'] ?: '-'));
        $this->info('No Sampel  : ' . ($filters['no_sampel'] ?: '-'));
        $this->info('Dry run    : ' . ($filters['dry_run'] ? 'YES' : 'NO'));
        $this->info('Limit      : ' . ($filters['limit'] ?: '-'));
        $this->info('Progress   : every ' . ($filters['progress_every'] > 0 ? $filters['progress_every'] : 'OFF') . ' group');

        if (!$filters['dry_run'] && !$filters['cfr'] && !$filters['no_sampel']) {
            $this->warn('Menjalankan update detail existing tanpa filter spesifik. Disarankan test dulu pakai --dry-run atau filter --cfr/--no-sampel.');
        }

        $filters['progress_callback'] = function (array $progress) {
            $this->line(sprintf(
                'PROGRESS %s %d/%d | matched=%d updated=%d skipped=%d failed=%d | current=%s',
                strtoupper($progress['type']),
                $progress['processed'],
                $progress['scanned'],
                $progress['matched'],
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
            $service = new LhpBackfillService($this->option('connection'), $this->option('order-connection') ?: null);
            $summary = $service->refreshKpgiDetails($filters);
        } catch (\Throwable $th) {
            $this->error($th->getMessage());
            return 1;
        }

        foreach ($summary['types'] as $typeName => $result) {
            $this->line('');
            $this->info(strtoupper($typeName));
            $this->line('Scanned         : ' . $result['scanned']);
            $this->line('Matched Headers : ' . $result['matched']);
            $this->line('Updated Headers : ' . $result['updated']);
            $this->line('Deleted Details : ' . $result['deleted_details']);
            $this->line('Inserted Details: ' . $result['inserted_details']);
            $this->line('Skipped         : ' . $result['skipped']);
            $this->line('Failed          : ' . $result['failed']);

            foreach (array_slice($result['messages'], 0, 20) as $message) {
                $this->line('- ' . $message);
            }

            if (count($result['messages']) > 20) {
                $this->line('- ... ' . (count($result['messages']) - 20) . ' pesan lain disembunyikan');
            }
        }

        $this->line('');
        $this->info('TOTAL');
        $this->line('Scanned         : ' . $summary['total']['scanned']);
        $this->line('Matched Headers : ' . $summary['total']['matched']);
        $this->line('Updated Headers : ' . $summary['total']['updated']);
        $this->line('Deleted Details : ' . $summary['total']['deleted_details']);
        $this->line('Inserted Details: ' . $summary['total']['inserted_details']);
        $this->line('Skipped         : ' . $summary['total']['skipped']);
        $this->line('Failed          : ' . $summary['total']['failed']);
        $this->info('===== Finish Refresh KPGI Detail =====');

        return $summary['total']['failed'] > 0 ? 1 : 0;
    }
}