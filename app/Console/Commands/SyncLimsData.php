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
                            {--limit=300 : Jumlah maksimal data Order Header yang ditarik} 
                            {--month= : Bulan spesifik (1-12)} 
                            {--year= : Tahun spesifik}';
                            
    protected $description = 'Sinkronisasi data Order Header & Detail dari DB Utama ke DB LIMS dan pembersihan data parameter tertentu';

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
            ->where('no_document', 'LIKE', '%/QT/%')
            ->limit($limit)
            ->get();

        if ($headers->isEmpty()) {
            $this->info("Tidak ada data Order Header pada {$month}/{$year} yang perlu disinkronisasi.");
            return 0;
        }

        $this->info("Ditemukan {$headers->count()} Order Header. Memulai proses copy...");
        $bar = $this->output->createProgressBar($headers->count());
        $bar->start();

        $successCount = 0;
        $failedOrders = [];

        // Tahun saat ini untuk referensi "Tahun Lama"
        $currentYear = (int) date('Y');

        // 1. FASE COPY DATA KE LIMS
        foreach ($headers as $header) {
            try {
                // Passing $year dan $currentYear ke dalam closure
                DB::connection('lims')->transaction(function () use ($header, $year, $currentYear) {
                    
                    $details = OrderDetail::where('id_order_header', $header->id)
                        ->whereNotIn('kategori_2', ['6-Padatan', '8-Tanah', '9-Pangan'])
                        // ->where('status', 3)
                        ->where('is_active', 1)
                        ->get();

                    if ($details->isEmpty()) {
                        return; 
                    }

                    LimsOrderHeader::updateOrCreate(['id' => $header->id], $header->toArray());
                    
                    foreach ($details as $detail) {
                        $detailData = $detail->toArray();
                        
                        // Jika tahun inputan lebih kecil dari tahun saat ini (Tahun Lama), paksa status jadi 3
                        if ($year < $currentYear) {
                            $detailData['status'] = 3;
                        }
                        
                        LimsOrderDetail::updateOrCreate(['id' => $detail->id], $detailData);
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


        // 2. FASE PEMBERSIHAN (CLEANUP) DATA DI LIMS
        $this->info("=== Memulai Fase Pembersihan (Cleanup) ===");
        
        $forbiddenParams = [
            'Rd - Alfa Beta',
            'Rd Alfa',
            'Rd Beta',
            'Rd - Alfa NS1',
            'Rd - Alfa',
            'Rd - Beta',
            'Ergonomi',
            'Psikologi'
        ];

        // Query mencari Order Detail di LIMS yang mengandung parameter terlarang
        $queryCleanup = LimsOrderDetail::query();
        foreach ($forbiddenParams as $param) {
            $queryCleanup->orWhereRaw("JSON_SEARCH(parameter, 'one', ?) IS NOT NULL", ["%;{$param}"]);
        }

        // Ambil ID Header-nya saja secara unik
        $forbiddenHeaderIds = $queryCleanup->pluck('id_order_header')->unique();

        if ($forbiddenHeaderIds->isNotEmpty()) {
            $this->warn("Ditemukan {$forbiddenHeaderIds->count()} Order yang mengandung parameter Radioaktif. Melakukan penghapusan...");
            
            DB::connection('lims')->transaction(function () use ($forbiddenHeaderIds) {
                // Hapus Detail-nya dulu
                LimsOrderDetail::whereIn('id_order_header', $forbiddenHeaderIds)->delete();
                // Hapus Header-nya
                LimsOrderHeader::whereIn('id', $forbiddenHeaderIds)->delete();
            });
            
            $this->info("Pembersihan selesai! Order H & D terkait berhasil dicabut.");
        } else {
            $this->info("Aman. Tidak ditemukan data dengan parameter Radioaktif di LIMS.");
        }
        $this->newLine();


        // 3. KESIMPULAN
        if (count($failedOrders) > 0) {
            $this->error("Sinkronisasi selesai dengan beberapa kendala!");
            $this->info("Berhasil Copy: {$successCount} data.");
            $this->error("Gagal: " . count($failedOrders) . " data.");
            $this->warn("Daftar No Order yang gagal: " . implode(', ', $failedOrders));
            $this->line("Silakan cek 'storage/logs/laravel.log' untuk detail error-nya.");
        } else {
            $this->info("Seluruh rangkaian periode {$month}/{$year} selesai dengan sukses 100%!");
            $this->info("Total Order diproses: {$successCount} data.");
        }

        return 0;
    }
}