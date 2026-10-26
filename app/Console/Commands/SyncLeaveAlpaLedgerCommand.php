<?php

namespace App\Console\Commands;

use App\Services\Greatday\LeaveAlpaSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncLeaveAlpaLedgerCommand extends Command
{
    protected $signature = 'greatday:sync-leave-alpa-ledger
                            {--from= : Y-m-d awal (default: awal bulan lalu)}
                            {--to= : Y-m-d akhir (default: hari ini)}
                            {--karyawan_id= : Opsional, satu karyawan}';

    protected $description = 'Catat alpa dari absensi ke ledger saldo cuti (potong cuti tahunan)';

    public function handle(): int
    {
        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))->startOfDay()
            : Carbon::now('Asia/Jakarta')->startOfDay();

        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : $to->copy()->subMonth()->startOfMonth();

        if ($to->lt($from)) {
            $this->error('Rentang tanggal tidak valid.');

            return 1;
        }

        $karyawanId = $this->option('karyawan_id') ? (int) $this->option('karyawan_id') : null;

        $this->line('Menghitung absensi (bisa beberapa menit untuk semua karyawan)…');

        $result = app(LeaveAlpaSyncService::class)->syncRange($from, $to, $karyawanId, $this->output);

        $this->info(sprintf(
            'Alpa ledger: created=%d skipped=%d employees=%d (%s — %s)',
            $result['created'],
            $result['skipped'],
            $result['employees'],
            $from->toDateString(),
            $to->toDateString()
        ));

        return 0;
    }
}
