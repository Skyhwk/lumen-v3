<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Models\Lims\OrderHeader as LimsOrderHeader;
use App\Models\Lims\OrderDetail as LimsOrderDetail;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use App\Services\SyncPersiapanService;

class BackfillPersiapanSampel extends Command
{
    protected $signature = 'sampling:backfill-persiapan
                            {--year= : Tahun target (contoh: 2025)}
                            {--month= : Bulan target (1-12)}
                            {--dry-run : Hanya menampilkan preview tanpa menyimpan data}
                            {--rollback : Menghapus semua data backfill (berdasarkan lims_2026) pada periode ini}
                            {--limit=300 : Jumlah maksimal OrderDetail yang diproses}';

    protected $description = 'Backfill data persiapan_sampel_header & detail untuk data order lama yang belum memiliki persiapan. Sumber data: DB LIMS, cross-check ke jadwal & sampling_plan di DB utama.';

    private $createdBy = 'lims_2026';

    public function handle()
    {
        $this->info('=== Backfill Persiapan Sampel ===');

        // --- 1. Input & Validasi ---
        $year  = (int) ($this->option('year')  ?? $this->ask('Masukkan Tahun target (contoh: 2025)', '2025'));
        $month = (int) ($this->option('month') ?? $this->ask('Masukkan Bulan target (1-12)', '1'));
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        $isRollback = $this->option('rollback');

        if ($month < 1 || $month > 12) {
            $this->error('Bulan tidak valid! Masukkan angka 1 s/d 12.');
            return 1;
        }

        $periodeAwal  = Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d');
        $periodeAkhir = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

        $this->info("Periode : {$periodeAwal} s/d {$periodeAkhir}");
        
        if ($isRollback) {
            return $this->handleRollback($periodeAwal, $periodeAkhir, $dryRun);
        }

        $this->info("Dry Run : " . ($dryRun ? 'YA (preview only)' : 'TIDAK (akan INSERT data)'));
        $this->info("Limit   : {$limit}");

        if (!$dryRun && !$this->confirm("Lanjutkan backfill untuk periode {$month}/{$year}?")) {
            $this->warn('Operasi dibatalkan.');
            return 1;
        }

        // --- 2. Ambil data dari LIMS OrderDetail ---
        $this->info('Mengambil data OrderDetail dari DB LIMS...');

        $limsOrderDetails = LimsOrderDetail::with([
                'orderHeader:id,no_order,no_document,nama_perusahaan,alamat_sampling,konsultan,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                }
            ])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
            ->limit($limit)
            ->get();

        if ($limsOrderDetails->isEmpty()) {
            $this->info("Tidak ada data OrderDetail LIMS pada periode {$month}/{$year}.");
            return 0;
        }

        $this->info("Ditemukan {$limsOrderDetails->count()} OrderDetail dari LIMS.");

        // --- 3. Grouping: per (no_order + tanggal_sampling + sampler) ---
        //         Mirip logic indexPersiapan di Controller
        $groupedData = [];

        foreach ($limsOrderDetails as $item) {
            if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {
                continue;
            }

            $orderHeader = $item->orderHeader;
            $periode = $item->periode ?? '';

            // Cari sampling plan yang cocok dengan periode
            $targetPlan = null;
            if ($periode) {
                $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
            }
            if (!$targetPlan) {
                $targetPlan = $orderHeader->sampling->first();
            }
            if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                continue;
            }

            // Loop jadwal: cari yang tanggal-nya cocok
            foreach ($targetPlan->jadwal as $schedule) {
                if ($schedule->tanggal !== $item->tanggal_sampling) {
                    continue;
                }

                $key = $item->no_order . '|' . $schedule->tanggal . '|' . $periode;

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'no_order'          => $item->no_order,
                        'no_quotation'      => $orderHeader->no_document,
                        'nama_perusahaan'   => $orderHeader->nama_perusahaan ?? '',
                        'tanggal_sampling'  => $schedule->tanggal,
                        'sampler_jadwal'    => $schedule->sampler ?? '',
                        'periode'           => $periode ?: null,
                        'kategori'          => implode(',', json_decode($schedule->kategori, true) ?? []),
                        'no_sampels'        => [],
                    ];
                } else {
                    // Gabungkan sampler jika jadwal di hari yang sama untuk order yang sama
                    $existingSamplers = explode(',', $groupedData[$key]['sampler_jadwal']);
                    $newSamplers      = explode(',', $schedule->sampler ?? '');
                    $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                    $groupedData[$key]['sampler_jadwal'] = implode(',', array_filter($merged));
                }

                $groupedData[$key]['no_sampels'][] = $item->no_sampel;
            }
        }

        // Deduplicate no_sampels
        foreach ($groupedData as &$group) {
            $group['no_sampels'] = array_values(array_unique($group['no_sampels']));
        }
        unset($group);

        $totalGroups = count($groupedData);
        $this->info("Terbentuk {$totalGroups} group persiapan yang perlu diproses.");

        if ($totalGroups === 0) {
            $this->info('Tidak ada group yang bisa di-backfill. Mungkin jadwal tidak ditemukan atau tidak match.');
            return 0;
        }

        // --- 4. Cek yang sudah ada (skip) ---
        $existingHeaders = PersiapanSampelHeader::where('is_active', 1)
            ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
            ->get()
            ->keyBy(function ($item) {
                $periode = $item->periode ?: '';
                return $item->no_order . '|' . $item->tanggal_sampling . '|' . $periode;
            });

        // --- 5. Process ---
        $bar = $this->output->createProgressBar($totalGroups);
        $bar->start();

        $berhasil = 0;
        $skip     = 0;
        $gagal    = 0;
        $persiapanSynced = 0;

        $syncService = new SyncPersiapanService();

        foreach ($groupedData as $key => $group) {
            $bar->advance();

            // Sudah ada? Skip.
            if ($existingHeaders->has($key)) {
                $skip++;
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [DRY-RUN] {$group['no_order']} | {$group['tanggal_sampling']} | Sampler: {$group['sampler_jadwal']} | Sampels: " . implode(', ', $group['no_sampels']));
                $berhasil++;
                continue;
            }

            DB::beginTransaction();
            try {
                // --- 5a. Sync persiapan column di OrderDetail (via LIMS model) ---
                $noSampels = $group['no_sampels'];

                // Ambil OrderDetail dari LIMS untuk cek & sync column persiapan
                $orderDetails = LimsOrderDetail::whereIn('no_sampel', $noSampels)
                    ->where('is_active', true)
                    ->get();

                foreach ($orderDetails as $od) {
                    if (!$od->persiapan || $od->persiapan === '[]') {
                        // Column persiapan masih kosong, sync di DB Utama
                        $syncService->sync([$od->no_sampel]);

                        // Ambil hasil dari DB Utama untuk di-copy ke LIMS DB
                        $mysqlOd = \App\Models\OrderDetail::where('no_sampel', $od->no_sampel)->first();
                        if ($mysqlOd && $mysqlOd->persiapan && $mysqlOd->persiapan !== '[]') {
                            $od->persiapan = $mysqlOd->persiapan;
                        }

                        // Set updated_by karena persiapan column baru di-generate
                        $od->updated_by = $this->createdBy;
                        $od->save();

                        $persiapanSynced++;
                    }
                }

                // --- 5b. INSERT persiapan_sampel_header ---
                $psh = new PersiapanSampelHeader();
                $psh->no_document    = $this->generateDocumentNumber($year, $month);
                $psh->no_order       = $group['no_order'];
                $psh->no_quotation   = $group['no_quotation'];
                $psh->tanggal_sampling = $group['tanggal_sampling'];
                $psh->nama_perusahaan  = $group['nama_perusahaan'];
                $psh->sampler_jadwal   = $group['sampler_jadwal'];
                $psh->periode          = $group['periode'];
                $psh->no_sampel        = json_encode($noSampels, JSON_UNESCAPED_SLASHES);
                $psh->is_active        = 1;
                $psh->created_by       = $this->createdBy;
                $psh->created_at       = Carbon::now();

                // Default JSON fields
                foreach (['plastik_benthos', 'media_petri_dish', 'media_tabung', 'masker', 'sarung_tangan_karet', 'sarung_tangan_bintik', 'tambahan'] as $jsonField) {
                    $psh->$jsonField = json_encode([]);
                }

                $psh->save();

                // --- 5c. INSERT persiapan_sampel_detail (per no_sampel) ---
                foreach ($noSampels as $noSampel) {
                    $od = $orderDetails->firstWhere('no_sampel', $noSampel);
                    if (!$od) continue;

                    // Build parameters JSON dari OrderDetail.persiapan
                    $persiapanData = json_decode($od->persiapan, true) ?? [];
                    
                    // Tentukan kategori dari OrderDetail.kategori_2
                    [$catId, $catName] = explode('-', $od->kategori_2 ?? '0-unknown');
                    $catName = strtolower($catName);

                    // Format parameters sesuai struktur yang diharapkan saveDetail
                    $parameters = [];
                    if ($catName === 'air') {
                        foreach ($persiapanData as $botol) {
                            $typeBotol = $botol['type_botol'] ?? 'unknown';
                            $parameters[$catName][$typeBotol] = [
                                'disiapkan' => $botol['disiapkan'] ?? 0,
                                'buffer'    => 0,
                                'file'      => $botol['file'] ?? null,
                            ];
                        }
                    } elseif (in_array($catName, ['udara', 'emisi'])) {
                        foreach ($persiapanData as $item) {
                            $paramName = $item['parameter'] ?? 'unknown';
                            $parameters[$catName][$paramName] = [
                                'disiapkan' => $item['disiapkan'] ?? 0,
                                'buffer'    => 0,
                                'file'      => $item['file'] ?? null,
                            ];
                        }
                    } else {
                        // Kategori lain (padatan, dll) — skip detail tapi tetap buat record
                        continue;
                    }

                    if (empty($parameters)) continue;

                    $psd = new PersiapanSampelDetail();
                    $psd->id_persiapan_sampel_header = $psh->id;
                    $psd->no_sampel   = $noSampel;
                    $psd->parameters  = json_encode($parameters);
                    $psd->is_active   = 1;
                    $psd->created_by  = $this->createdBy;
                    $psd->created_at  = Carbon::now();
                    $psd->save();
                }

                DB::commit();
                $berhasil++;

            } catch (\Throwable $th) {
                DB::rollBack();
                $gagal++;
                $this->newLine();
                $this->error("  [GAGAL] {$group['no_order']} | {$group['tanggal_sampling']} -> {$th->getMessage()} (line: {$th->getLine()})");
                Log::error("BackfillPersiapan GAGAL: {$group['no_order']}", [
                    'error' => $th->getMessage(),
                    'line'  => $th->getLine(),
                    'file'  => $th->getFile(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine(2);

        // --- 6. Summary ---
        $this->info('=== SUMMARY ===');
        $this->table(
            ['Metric', 'Jumlah'],
            [
                ['Total Group Ditemukan', $totalGroups],
                ['Berhasil di-insert',    $berhasil],
                ['Skip (sudah ada)',      $skip],
                ['Gagal',                 $gagal],
                ['OrderDetail.persiapan di-sync', $persiapanSynced],
            ]
        );

        if ($dryRun) {
            $this->warn('Mode DRY-RUN aktif. Tidak ada data yang disimpan.');
        }

        return 0;
    }

    /**
     * Generate nomor dokumen mengikuti tahun target.
     * Format: ISL/PS/YY-ROMAN/XXXX
     * Angka terakhir diambil dari MAX(id) + 1 di tabel persiapan_sampel_header.
     */
    private function generateDocumentNumber(int $year, int $month): string
    {
        $romanMonths = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
        $romanMonth  = $romanMonths[$month - 1];
        $yearShort   = substr((string) $year, -2); // 2025 -> 25

        // Ambil ID terakhir untuk menghindari duplikasi
        $latestPSH = PersiapanSampelHeader::where('is_active', 1)
            ->orderBy('id', 'desc')
            ->first();

        $nextId = $latestPSH ? $latestPSH->id + 1 : 1;

        return 'ISL/PS/' . $yearShort . '-' . $romanMonth . '/' . sprintf('%04d', $nextId);
    }

    private function handleRollback($periodeAwal, $periodeAkhir, $dryRun)
    {
        $this->warn("=== MEMULAI PROSES ROLLBACK ===");
        if (!$dryRun && !$this->confirm("PERINGATAN: Ini akan menghapus data yang digenerate oleh '{$this->createdBy}' pada periode {$periodeAwal} - {$periodeAkhir}. Lanjutkan?")) {
            $this->warn('Rollback dibatalkan.');
            return 1;
        }

        DB::beginTransaction();
        try {
            // 1. Ambil ID Header yang dibuat oleh backfill ini
            $headers = PersiapanSampelHeader::where('created_by', $this->createdBy)
                ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
                ->get();
            
            $headerIds = $headers->pluck('id')->toArray();
            
            // 2. Rollback OrderDetail (Kembalikan ke '[]')
            $limsOrderDetails = LimsOrderDetail::where('updated_by', $this->createdBy)
                ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
                ->get();
                
            $noSampels = $limsOrderDetails->pluck('no_sampel')->toArray();

            if ($dryRun) {
                $this->info("[DRY-RUN] Ditemukan " . count($headerIds) . " PersiapanSampelHeader untuk dihapus.");
                $this->info("[DRY-RUN] Ditemukan " . count($noSampels) . " OrderDetail (LIMS) untuk di-reset persiapan-nya.");
                return 0;
            }

            // Hapus Details & Headers
            if (!empty($headerIds)) {
                PersiapanSampelDetail::whereIn('id_persiapan_sampel_header', $headerIds)->delete();
                PersiapanSampelHeader::whereIn('id', $headerIds)->delete();
                $this->info("Berhasil menghapus " . count($headerIds) . " Header beserta Detail-nya.");
            } else {
                $this->info("Tidak ada Header yang perlu dihapus.");
            }

            // Reset OrderDetail (LIMS)
            if (!empty($noSampels)) {
                LimsOrderDetail::whereIn('no_sampel', $noSampels)->update([
                    'persiapan' => '[]',
                    'updated_by' => null
                ]);
                
                // Reset juga di MySQL Utama (karena SyncPersiapanService tadinya save ke situ)
                \App\Models\OrderDetail::whereIn('no_sampel', $noSampels)->update([
                    'persiapan' => '[]',
                    // MySQL OrderDetail biasanya nggak punya updated_by lims_2026, 
                    // tapi kita paksa reset by no_sampel saja karena kita tau mana yg baru di-sync
                ]);

                $this->info("Berhasil mereset kolom persiapan pada " . count($noSampels) . " OrderDetail.");
            } else {
                $this->info("Tidak ada OrderDetail yang perlu di-reset.");
            }

            DB::commit();
            $this->info("=== ROLLBACK SELESAI ===");
            return 0;

        } catch (\Throwable $th) {
            DB::rollBack();
            $this->error("Rollback Gagal: " . $th->getMessage() . " pada baris " . $th->getLine());
            return 1;
        }
    }
}
