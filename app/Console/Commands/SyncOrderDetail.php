<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\OrderDetail;
use App\Models\OrderHeader;

class SyncOrderDetail extends Command
{
    protected $signature = 'sync:order-detail {--year=} {--month=}';

    public function handle()
    {
        $this->info('Syncing order detail from jadwal...');

        $year = $this->option('year');
        $month = $this->option('month');

        if ($year && $month) {
            $noOrders = OrderDetail::whereYear('tanggal_sampling', $year)
                ->whereMonth('tanggal_sampling', $month)
                ->whereHas('orderHeader', function($query) {
                    $query->whereColumn('order_detail.no_quotation', '!=', 'order_header.no_document');
                })
                ->where('order_detail.is_active', 1)
                ->get()->pluck('no_order')->unique()->toArray();
                dd($noOrders);
        } else {
            $this->error('Year and month are required');
            return;
        }
    }
}