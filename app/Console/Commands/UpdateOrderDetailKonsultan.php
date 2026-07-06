<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateOrderDetailKonsultan extends Command
{
    private const CUTOFF_DATE = '2025-01-01';

    protected $signature = 'order-detail:update-konsultan {--dry-run : Tampilkan jumlah data tanpa melakukan update}';
    protected $description = 'Update order_detail.konsultan dari order_header untuk detail yang masih NULL';

    public function handle()
    {
        $this->info('===== Start Command: UpdateOrderDetailKonsultan =====');
        $this->info('Tanggal sampling > ' . self::CUTOFF_DATE);

        $query = $this->baseQuery();
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Tidak ada order_detail yang perlu diupdate.');
            $this->info('===== Finish Command: UpdateOrderDetailKonsultan =====');
            return 0;
        }

        $this->info("Total order_detail yang akan diupdate: {$total}");

        if ($this->option('dry-run')) {
            $this->info('Dry-run aktif, tidak ada data yang diupdate.');
            $this->info('===== Finish Command: UpdateOrderDetailKonsultan =====');
            return 0;
        }

        $updated = DB::transaction(function () {
            return $this->baseQuery()->update([
                'od.konsultan' => DB::raw('oh.konsultan'),
            ]);
        });

        $this->info("Berhasil update {$updated} order_detail.");
        $this->info('Waktu selesai: ' . Carbon::now()->toDateTimeString());
        $this->info('===== Finish Command: UpdateOrderDetailKonsultan =====');

        return 0;
    }

    private function baseQuery()
    {
        return DB::table('order_detail as od')
            ->join('order_header as oh', 'oh.id', '=', 'od.id_order_header')
            ->whereNull('od.konsultan')
            ->whereNotNull('oh.konsultan')
            ->whereRaw("TRIM(oh.konsultan) != ''")
            ->whereDate('od.tanggal_sampling', '>', self::CUTOFF_DATE);
    }
}
