<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

use App\Models\OrderDetail;
use App\Models\Jadwal;

class SyncOrderDetaolFromJadwal extends Command
{
    protected $signature = 'sync-order-detail';
    protected $description = 'Sync Order Detail from Jadwal';

    public function handle()
    {
        $this->info('Start checking Order Detail...');

        $query = OrderDetail::query()
            ->select(['id', 'no_quotation', 'no_sampel', 'kategori_3', 'tanggal_sampling'])
            ->whereNull('tanggal_terima')
            ->where('is_active', 1)
            ->whereNotIn('kategori_1', ['SD', 'SP'])
            ->whereNotIn('kategori_3', ['118-Psikologi'])
            ->whereMonth('tanggal_sampling', '12')
            ->whereYear('tanggal_sampling', '2026')
            ->orderByDesc('tanggal_sampling');

        $query->chunk(500, function ($orderDetails) {
            $quotationNumbers = $orderDetails->pluck('no_quotation')->unique()->values();

            $jadwalByQuotation = Jadwal::query()
                ->select(['id', 'no_quotation', 'kategori', 'tanggal'])
                ->whereIn('no_quotation', $quotationNumbers)
                ->where('is_active', 1)
                ->get()
                ->groupBy('no_quotation');

            foreach ($orderDetails as $item) {
                $kategoriParts = explode('-', $item->kategori_3);
                $sampelParts = explode('/', $item->no_sampel);
                $kategori = ($kategoriParts[1] ?? '') . ' - ' . ($sampelParts[1] ?? '');

                $matchedJadwal = ($jadwalByQuotation->get($item->no_quotation) ?? collect())
                    ->first(function ($jadwal) use ($kategori) {
                        return strpos($jadwal->kategori, $kategori) !== false;
                    });

                if ($matchedJadwal && $matchedJadwal->tanggal != $item->tanggal_sampling) {
                    $this->info('Tanggal tidak singkron, singkronisasi order detail dari jadwal...');
                    $this->info('No Sampel: ' . $item->no_sampel);
                    $this->info('Kategori: ' . $kategori);
                    $this->info('Tanggal Sampling (lama): ' . $item->tanggal_sampling);
                    $this->info('Tanggal Jadwal (baru): ' . $matchedJadwal->tanggal);
                    OrderDetail::where('id', $item->id)->update([
                        'tanggal_sampling' => $matchedJadwal->tanggal,
                    ]);
                }
            }
        });
    }
}