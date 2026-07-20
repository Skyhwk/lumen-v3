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

class SyncLimsData extends Command
{
    protected $signature = 'lims:sync 
                            {--limit=50 : Jumlah maksimal data Order Header yang ditarik} 
                            {--month= : Bulan spesifik (1-12)} 
                            {--year= : Tahun spesifik}';
                            
    protected $description = 'Sinkronisasi data Order Header & Detail dari DB Utama ke DB LIMS';

    public function handle()
    {
        $limit = $this->option('limit');
        $this->info("=== Persiapan Sinkronisasi Data LIMS ===");

        $inputMonth = $this->option('month') ?? $this->ask('Masukkan Bulan yang ingin ditarik (1-12)', date('n'));
        $inputYear = $this->option('year') ?? $this->ask('Masukkan Tahun (contoh: 2026)', date('Y'));

        $month = (int) $inputMonth;
        $year = (int) $inputYear;

        if ($month < 1 || $month > 12) {
            $this->error('Bulan tidak valid! Harap masukkan angka 1 sampai 12.');
            return 1;
        }

        if (!$this->confirm("Tarik maksimal {$limit} data untuk periode Bulan {$month} Tahun {$year}. Lanjutkan?")) {
            $this->warn('Operasi dibatalkan.');
            return 1;
        }

        $headers = OrderHeader::where('is_revisi', 0)
            ->whereMonth('tanggal_order', $month)
            ->whereYear('tanggal_order', $year)
            ->limit($limit)
            ->get();

        if ($headers->isEmpty()) {
            $this->info("Tidak ada data Order Header pada {$month}/{$year} yang perlu disinkronisasi.");
            return 0;
        }

        $this->info("Ditemukan {$headers->count()} Order Header. Memulai proses...");
        $bar = $this->output->createProgressBar($headers->count());
        $bar->start();

        $successCount = 0;
        $failedOrders = [];

        foreach ($headers as $header) {
            try {
                DB::connection('lims')->transaction(function () use ($header) {
                    
                    $details = OrderDetail::where('id_order_header', $header->id)
                        ->whereNotIn('kategori_2', ['6-Padatan', '8-Tanah', '9-Pangan'])
                        // ->where('status', 3)
                        ->where('is_active', 1)
                        ->get();

                    if ($details->isEmpty()) {
                        return; 
                    }

                    // A. OrderHeader & Detail Saja
                    LimsOrderHeader::updateOrCreate(['id' => $header->id], $header->toArray());
                    foreach ($details as $detail) {
                        LimsOrderDetail::updateOrCreate(['id' => $detail->id], $detail->toArray());
                    }
                });

                $successCount++;
                
            } catch (Exception $e) {
                $failedOrders[] = $header->no_order;

                Log::error("[LIMS SYNC FAILED] Gagal sinkronisasi Order.", [
                    'id_order'      => $header->id,
                    'no_order'      => $header->no_order,
                    'error_message' => $e->getMessage(),
                    'file'          => $e->getFile(),
                    'line'          => $e->getLine()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (count($failedOrders) > 0) {
            $this->error("Sinkronisasi selesai dengan beberapa kendala!");
            $this->info("Berhasil: {$successCount} data.");
            $this->error("Gagal: " . count($failedOrders) . " data.");
            $this->warn("Daftar No Order yang gagal: " . implode(', ', $failedOrders));
            $this->line("Silakan cek 'storage/logs/laravel.log' untuk detail error-nya.");
        } else {
            $this->info("Sinkronisasi periode {$month}/{$year} selesai dengan sukses 100%!");
            $this->info("Total diproses: {$successCount} data.");
        }

        return 0;
    }
}