<?php

namespace App\Console\Commands;

use App\Models\MasterKategori;
use App\Models\MonitorKeterlambatanAnalisa;
use App\Services\MonitorKeterlambatanAnalisaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CollectMonitorKeterlambatanAnalisa extends Command
{
    protected $signature = 'collect:monitor-keterlambatan-analisa
                            {--kategori= : Kategori spesifik, format id-nama (contoh: 1-Air)}
                            {--dry-run : Simulasi tanpa menulis ke database}';

    protected $description = 'Kumpulkan data keterlambatan hasil analisa dari sampel yang sudah di-scan lab';

    public function handle(MonitorKeterlambatanAnalisaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $kategoriFilter = $this->option('kategori');

        $this->info('Mulai collect monitor keterlambatan analisa');
        $this->info('Periode: ' . MonitorKeterlambatanAnalisaService::START_DATE . ' s/d ' . Carbon::now()->toDateString());

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

            $records = $service->collectDelayedRecords($kategori);
            $this->info('Ditemukan ' . count($records) . ' record keterlambatan');

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($kategori, $records, &$totalUpserted, &$totalDeactivated) {
                $activeKeys = collect($records)->map(
                    fn ($r) => $r['no_sampel'] . '|' . $r['nama_parameter']
                )->toArray();

                $deactivated = MonitorKeterlambatanAnalisa::where('kategori_2', $kategori)
                    ->where('is_active', true)
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
                    MonitorKeterlambatanAnalisa::upsert(
                        $chunk,
                        ['no_sampel', 'nama_parameter'],
                        ['kategori_2', 'ftc_laboratory', 'ftc_verifier', 'is_active', 'updated_at']
                    );
                    $totalUpserted += count($chunk);
                }
            });
        }

        $this->line('');
        $this->info('Selesai.');
        $this->info("Upserted: {$totalUpserted} | Deactivated: {$totalDeactivated}");

        return 0;
    }
}
