<?php

namespace App\Console\Commands;

use App\Models\MasterKategori;
use App\Models\LogAnalisa;
use App\Services\MonitorKeterlambatanAnalisaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CollectMonitorKeterlambatanAnalisa extends Command
{
    protected $signature = 'collect:monitor-keterlambatan-analisa
                            {--kategori= : Kategori spesifik, format id-nama (contoh: 1-Air)}
                            {--date= : Tanggal spesifik (Y-m-d)}
                            {--from= : Tanggal awal (Y-m-d)}
                            {--to= : Tanggal akhir (Y-m-d)}
                            {--dry-run : Simulasi tanpa menulis ke database}';

    protected $description = 'Kumpulkan log analisa hasil analisa dari sampel yang sudah di-scan lab';

    public function handle(MonitorKeterlambatanAnalisaService $service): int
    {
        $startedAt = microtime(true);
        $dryRun = (bool) $this->option('dry-run');
        $kategoriFilter = $this->option('kategori');

        [$startDate, $endDate] = $this->resolveDateRange();

        $this->info('Mulai collect log analisa');
        $this->info('Periode tanggal jadwal: ' . $startDate->toDateString() . ' s/d ' . $endDate->toDateString());

        $kategoris = MasterKategori::where('is_active', 1)
            ->when($kategoriFilter, fn ($q) => $q->whereRaw("CONCAT(id, '-', nama_kategori) = ?", [$kategoriFilter]))
            ->get()
            ->map(fn ($k) => $k->id . '-' . $k->nama_kategori);

        if ($kategoris->isEmpty()) {
            $this->error('Tidak ada kategori aktif ditemukan.');
            return 1;
        }

        $totalUpserted = 0;
        $totalDeactivated = 0;

        foreach ($kategoris as $kategori) {
            $this->line('');
            $this->info("Memproses kategori: {$kategori}");

            $records = $service->collectLogRecords($kategori, $startDate, $endDate);
            $this->info('Ditemukan ' . count($records) . ' record log analisa');

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($kategori, $records, $startDate, $endDate, &$totalUpserted, &$totalDeactivated) {
                $activeKeys = collect($records)->map(
                    fn ($r) => $r['no_sampel'] . '|' . $r['nama_parameter']
                )->toArray();

                $deactivated = LogAnalisa::where('kategori_2', $kategori)
                    ->where('is_active', true)
                    ->whereBetween('tanggal_jadwal', [
                        $startDate->toDateString(),
                        $endDate->toDateString(),
                    ])
                    ->get()
                    ->filter(function ($row) use ($activeKeys) {
                        $key = $row->no_sampel . '|' . $row->nama_parameter;
                        return !in_array($key, $activeKeys);
                    });

                foreach ($deactivated as $row) {
                    $row->update(['is_active' => false, 'updated_at' => Carbon::now()]);
                    $totalDeactivated++;
                }

                foreach (array_chunk($records, 500) as $chunk) {
                    LogAnalisa::upsert(
                        $chunk,
                        ['no_sampel', 'nama_parameter'],
                        [
                            'id_parameter',
                            'kategori_2',
                            'tanggal_jadwal',
                            'ftc_laboratory',
                            'ftc_verifier',
                            'input_analisa',
                            'is_active',
                            'updated_at',
                        ]
                    );
                    $totalUpserted += count($chunk);
                }
            });
        }

        $this->line('');
        $this->info('Selesai.');
        $this->info("Upserted: {$totalUpserted} | Deactivated: {$totalDeactivated}");
        $this->info('Durasi: ' . $this->formatDuration(microtime(true) - $startedAt));

        return 0;
    }

    private function resolveDateRange(): array
    {
        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'));

            return [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        }

        $endDate = $this->option('to')
            ? Carbon::parse($this->option('to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $startDate = $this->option('from')
            ? Carbon::parse($this->option('from'))->startOfDay()
            : Carbon::now()->subDays(MonitorKeterlambatanAnalisaService::COLLECT_DAYS)->startOfDay();

        return [$startDate, $endDate];
    }

    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . ' detik';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = round($seconds % 60, 2);

        return "{$minutes} menit {$remaining} detik";
    }
}
