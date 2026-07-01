<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SyncQsdRevenue extends Command
{
    protected $signature = 'qsd:sync-revenue';
    protected $description = 'Migrate initial data from daily_qsd to log with running total logic';

    public function handle()
    {
        $this->info('Memulai migrasi data awal daily_qsd...');

        $count = 0;
        $now = Carbon::now();
        
        // Ambil string periode dan angka bulan/tahun saat ini
        $currentPeriodStr = $now->format('Y-m'); // Contoh: '2026-06'
        $currentMonth = $now->format('m');       // Contoh: '06'
        $currentYear = $now->format('Y');        // Contoh: '2026'

        $lastOrder = null;
        $runningTotal = 0;

        // PENTING: Urutkan berdasarkan no_order dan tanggal agar penjumlahannya benar
        DB::table('daily_qsd')
            ->where(function ($query) use ($currentPeriodStr, $currentMonth, $currentYear) {
                    // Kondisi 1: Ambil yang 'periode'-nya adalah bulan ini
                    $query->where(function ($subQuery) use ($currentMonth, $currentYear) {
                            $subQuery->whereMonth('tanggal_kelompok', $currentMonth)
                                    ->whereYear('tanggal_kelompok', $currentYear);
                        });
                })
            ->orderBy('no_order')
            ->orderBy('created_at')
            ->chunk(500, function ($records) use (&$count, $now, $currentPeriodStr, &$lastOrder, &$runningTotal) {
                $insertData = [];

                foreach ($records as $row) {
                    // --- 1. LOGIC PERIODE ---
                    $periodeFix = $currentPeriodStr;
                    if (!empty($row->tanggal_kelompok)) {
                        $periodeFix = Carbon::parse($row->tanggal_kelompok)->format('Y-m');
                    }
                    // --- 2. LOGIC RUNNING TOTAL (AKUMULASI) ---
                    $currentRevenue = (float) $row->total_revenue;
                    // Jika ini adalah order yang berbeda dari sebelumnya, reset running total ke 0
                    if ($lastOrder !== $row->no_order) {
                        $lastOrder = $row->no_order;
                        $runningTotal = 0;
                    }
                    // Tambahkan total_revenue saat ini ke dalam running total
                    $runningTotal += $currentRevenue;

                    $insertData[] = [
                        'no_order'   => $row->no_order,
                        'periode'    => $periodeFix,
                        'revenue'    => $currentRevenue,       // Nilai yang ditambahkan
                        'status'     => 'penambahan',          // Semua data awal dianggap penambahan
                        'total'      => $runningTotal,         // Hasil akumulasi (Total sebelumnya + Revenue saat ini)
                        'created_at' => $row->created_at ?? $now,
                    ];
                }

                if (!empty($insertData)) {
                    DB::table('qsd_revenue_transaction_log')->insert($insertData);
                    $count += count($insertData);
                    
                    $this->info("Berhasil memindahkan {$count} baris sejauh ini...");
                }
            });

        $this->info("Migrasi selesai! Total data dibuat: {$count} baris.");
    }
}