<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

use App\Models\OrderHeader;
use App\Models\OrderDetail;

use App\Models\Lims\OrderHeader as LimsOrderHeader;
use App\Models\Lims\OrderDetail as LimsOrderDetail;

class SyncSpecificLimsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lims:sync-specific 
                            {orders : Daftar no_order yang ingin disinkronisasi, pisahkan dengan koma} 
                            {--dry-run : Menjalankan command tanpa menyimpan ke database (preview)}
                            {--rollback : Menghapus data no_order tersebut dari database LIMS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi atau Rollback data Order Header & Detail ke DB LIMS untuk no_order spesifik';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $ordersInput = $this->argument('orders');
        $isDryRun = $this->option('dry-run');
        $isRollback = $this->option('rollback');

        // Pisahkan input string menjadi array berdasarkan koma
        // Hapus whitespace berlebih di setiap elemen
        $orderNumbers = array_map('trim', explode(',', $ordersInput));
        $orderNumbers = array_filter($orderNumbers); // Hapus elemen kosong

        if (empty($orderNumbers)) {
            $this->error('Tidak ada no_order yang diberikan.');
            return 1;
        }

        $this->info("=== Memproses " . count($orderNumbers) . " no_order ===");
        if ($isDryRun) {
            $this->warn('*** DRY RUN MODE AKTIF: Tidak ada perubahan ke database ***');
        }

        if ($isRollback) {
            return $this->handleRollback($orderNumbers, $isDryRun);
        } else {
            return $this->handleSync($orderNumbers, $isDryRun);
        }
    }

    private function handleRollback($orderNumbers, $isDryRun)
    {
        $this->info("Mode Rollback diaktifkan. Mencari data di LIMS...");

        $headersLims = LimsOrderHeader::whereIn('no_order', $orderNumbers)->get();

        if ($headersLims->isEmpty()) {
            $this->info("Tidak ada data yang ditemukan di LIMS untuk no_order tersebut.");
            return 0;
        }

        $headerIds = $headersLims->pluck('id')->toArray();
        $detailCount = LimsOrderDetail::whereIn('id_order_header', $headerIds)->count();

        $this->info("Ditemukan {$headersLims->count()} Order Header dan {$detailCount} Order Detail di LIMS.");

        if ($isDryRun) {
            $this->info("[DRY RUN] Data berikut AKAN DIHAPUS dari LIMS:");
            foreach ($headersLims as $h) {
                $this->line("- " . $h->no_order);
            }
            return 0;
        }

        if (!$this->confirm("Anda yakin ingin menghapus data tersebut dari LIMS?")) {
            $this->warn('Operasi dibatalkan.');
            return 1;
        }

        DB::connection('lims')->transaction(function () use ($headerIds) {
            LimsOrderDetail::whereIn('id_order_header', $headerIds)->delete();
            LimsOrderHeader::whereIn('id', $headerIds)->delete();
        });

        $this->info("Proses Rollback berhasil! Data telah dihapus dari LIMS.");
        return 0;
    }

    private function handleSync($orderNumbers, $isDryRun)
    {
        $this->info("Mode Sinkronisasi diaktifkan. Mengambil data dari DB Utama...");

        $headers = OrderHeader::whereIn('no_order', $orderNumbers)->get();
        
        $foundOrders = $headers->pluck('no_order')->toArray();
        $notFoundOrders = array_diff($orderNumbers, $foundOrders);

        if (!empty($notFoundOrders)) {
            $this->warn("No Order berikut tidak ditemukan di DB Utama: " . implode(', ', $notFoundOrders));
        }

        if ($headers->isEmpty()) {
            $this->error("Gagal sinkronisasi: Semua no_order yang diberikan tidak ditemukan di DB Utama.");
            return 1;
        }

        $this->info("Ditemukan {$headers->count()} Order Header. Memulai proses...");

        $successCount = 0;
        $failedOrders = [];
        $currentYear = (int) date('Y');

        foreach ($headers as $header) {
            try {
                $details = OrderDetail::where('id_order_header', $header->id)
                    ->whereNotIn('kategori_2', ['6-Padatan', '8-Tanah', '9-Pangan'])
                    ->where('is_active', 1)
                    ->get();

                if ($details->isEmpty()) {
                    $this->line("SKIPPED: {$header->no_order} (Tidak ada Order Detail yang valid untuk disinkronisasi)");
                    continue;
                }

                if ($isDryRun) {
                    $this->info("[DRY RUN] AKAN DISINKRONISASI: {$header->no_order} dengan {$details->count()} Detail.");
                    continue;
                }

                // Eksekusi jika bukan dry run
                DB::connection('lims')->transaction(function () use ($header, $details, $currentYear) {
                    LimsOrderHeader::updateOrCreate(['id' => $header->id], $header->toArray());
                    
                    $year = (int) date('Y', strtotime($header->tanggal_order));

                    foreach ($details as $detail) {
                        $detailData = $detail->toArray();
                        
                        // Rule sama seperti SyncLimsData
                        if ($year < $currentYear) {
                            $detailData['status'] = 3;
                            $detailData['is_approve'] = true;
                            $detailData['approved_by'] = 'lims_2026';
                        }
                        
                        LimsOrderDetail::updateOrCreate(['id' => $detail->id], $detailData);
                    }
                });

                $this->info("SUCCESS: {$header->no_order}");
                $successCount++;
                
            } catch (Exception $e) {
                $failedOrders[] = $header->no_order;
                $this->error("FAILED: {$header->no_order} | Error: " . $e->getMessage());

                Log::error("[LIMS SPECIFIC SYNC FAILED] Gagal sinkronisasi Order.", [
                    'id_order'      => $header->id,
                    'no_order'      => $header->no_order,
                    'error_message' => $e->getMessage(),
                ]);
            }
        }

        if (!$isDryRun) {
            $this->newLine();
            $this->info("=== Proses Pembersihan Parameter Terlarang ===");
            $this->cleanupForbiddenParameters($headers->pluck('id')->toArray(), $isDryRun);
            
            $this->newLine();
            $this->info("Sinkronisasi Selesai!");
            $this->line("Berhasil: {$successCount} data");
            if (count($failedOrders) > 0) {
                $this->error("Gagal: " . count($failedOrders) . " data");
                $this->warn("Daftar gagal: " . implode(', ', $failedOrders));
            }
        }

        return 0;
    }

    private function cleanupForbiddenParameters($headerIds, $isDryRun)
    {
        $forbiddenParams = [
            'Rd - Alfa Beta', 'Rd Alfa', 'Rd Beta', 'Rd - Alfa NS1', 
            'Rd - Alfa', 'Rd - Beta', 'Ergonomi', 'Psikologi', 
            'Plankton', 'Benthos', 'Bentos', 'Necton'
        ];

        $queryCleanup = LimsOrderDetail::whereIn('id_order_header', $headerIds);
        
        $queryCleanup->where(function($q) use ($forbiddenParams) {
            foreach ($forbiddenParams as $param) {
                $q->orWhereRaw("JSON_SEARCH(parameter, 'one', ?) IS NOT NULL", ["%;{$param}"]);
            }
        });

        $forbiddenHeaderIds = $queryCleanup->pluck('id_order_header')->unique();

        if ($forbiddenHeaderIds->isNotEmpty()) {
            if ($isDryRun) {
                $this->warn("[DRY RUN] Ditemukan {$forbiddenHeaderIds->count()} Order yang mengandung parameter terlarang dan AKAN DIHAPUS (Rollback otomatis).");
                return;
            }

            $this->warn("Ditemukan {$forbiddenHeaderIds->count()} Order yang mengandung parameter terlarang (Radioaktif, dsb). Melakukan rollback otomatis...");
            
            DB::connection('lims')->transaction(function () use ($forbiddenHeaderIds) {
                LimsOrderDetail::whereIn('id_order_header', $forbiddenHeaderIds)->delete();
                LimsOrderHeader::whereIn('id', $forbiddenHeaderIds)->delete();
            });
            
            $this->info("Pembersihan selesai! Order yang terlarang berhasil dicabut dari LIMS.");
        } else {
            if (!$isDryRun) {
                $this->info("Aman. Tidak ditemukan data dengan parameter terlarang di list ini.");
            }
        }
    }
}
