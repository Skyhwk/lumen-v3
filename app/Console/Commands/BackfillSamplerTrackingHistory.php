<?php

namespace App\Console\Commands;

use App\Services\SamplerTrackingHistoryBackfill;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillSamplerTrackingHistory extends Command
{
    protected $signature = 'sampler-tracking:backfill-history
        {--from= : Tanggal sampling mulai YYYY-MM-DD (default satu bulan sebelum --to)}
        {--to= : Tanggal sampling akhir YYYY-MM-DD, inklusif (default hari ini WIB)}
        {--dry-run : Hitung entri yang akan ditambahkan tanpa menulis data}';

    protected $description = 'Isi meta_history dari session dan event lama; tidak merekonstruksi perubahan jadwal yang tidak memiliki bukti.';

    public function handle(SamplerTrackingHistoryBackfill $service)
    {
        try {
            $to = $this->option('to') ?: Carbon::now('Asia/Jakarta')->toDateString();
            $toDate = $this->parseDate($to);
            $from = $this->option('from') ?: $toDate->copy()->subMonthNoOverflow()->toDateString();
            $this->parseDate($from);
            if ($from > $to) throw new \InvalidArgumentException('--from harus sebelum atau sama dengan --to.');
            $this->line('Tanggal sampling: ' . $from . ' s/d ' . $to . ' (termasuk kedua tanggal).');
            $this->warn('Backfill hanya memakai bukti tersimpan. Snapshot durasi/jadwal adalah kondisi saat command dijalankan.');
            $stats = $service->run($from, $to, (bool) $this->option('dry-run'));
            $this->info('Session diperiksa: ' . $stats['sessions']);
            $this->info(($this->option('dry-run') ? 'Entri yang akan ditambahkan: ' : 'Entri ditambahkan: ') . $stats['entries']);
            return 0;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return 1;
        }
    }

    private function parseDate(string $value): Carbon
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException('Tanggal wajib berformat YYYY-MM-DD.');
        }
        $date = Carbon::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        if (!$date || $date->toDateString() !== $value) {
            throw new \InvalidArgumentException('Tanggal tidak valid: ' . $value);
        }
        return $date;
    }
}
