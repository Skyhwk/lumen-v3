<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PersiapanSampelHeader;
use App\Models\Jadwal;
use Illuminate\Support\Facades\Log;

class AuditPersiapanCommand extends Command
{
    /**
     * Nama perintah yang akan diketik di terminal
     */
    protected $signature = 'audit:persiapan';

    /**
     * Deskripsi perintah
     */
    protected $description = 'Melakukan audit pada data Persiapan Sampel Header yang menggantung atau tidak sinkron dengan Jadwal';
    /**
     * kontrak
     */
    // public function handle()
    // {
    //     $this->info('Memulai proses audit data...');

    //     // Opsional: Batasi periode
    //     // $periodeTarget = ['2025-06','2025-07','2025-08','2025-09', '2025-10', '2025-11', '2025-12']; 
    //     $periodeTarget = ['2025-01','2025-02','2025-03','2025-04', '2025-05']; 

    //     // Tarik semua persiapan aktif
    //     $semuaPersiapanAktif = PersiapanSampelHeader::where('is_active', 1)
    //         ->whereIn('periode', $periodeTarget)
    //         ->get();

    //     $kumpulanHantu = [];
    //     $kumpulanTidakSinkron = [];

    //     // Progress bar agar terlihat keren di terminal
    //     $bar = $this->output->createProgressBar(count($semuaPersiapanAktif));
    //     $bar->start();

    //     foreach ($semuaPersiapanAktif as $persiapan) {
    //         $jadwalSamplers = Jadwal::where('no_quotation', $persiapan->no_quotation)
    //             ->where('tanggal', $persiapan->tanggal_sampling)
    //             ->where('is_active', 1)
    //             ->pluck('sampler')
    //             ->toArray();

    //         // LOGIKA 1: JADWAL KOSONG (HANTU)
    //         if (empty($jadwalSamplers)) {
    //             $kumpulanHantu[] = [
    //                 'id_persiapan' => $persiapan->id,
    //                 'no_quotation' => $persiapan->no_quotation,
    //                 'tanggal'      => $persiapan->tanggal_sampling,
    //             ];
    //             $bar->advance();
    //             continue; 
    //         }

    //         // LOGIKA 2: TIDAK SINKRON
    //         $stringSamplerPersiapan = $persiapan->sampler_jadwal ?? '';
    //         $arrayPersiapan = array_filter(array_map('trim', explode(',', $stringSamplerPersiapan)));
    //         $arrayJadwal = array_filter(array_map('trim', $jadwalSamplers));

    //         sort($arrayPersiapan);
    //         sort($arrayJadwal);

    //         if ($arrayPersiapan !== $arrayJadwal) {
    //             $kumpulanTidakSinkron[] = [
    //                 'id_persiapan'      => $persiapan->id,
    //                 'sampler_persiapan' => implode(', ', $arrayPersiapan),
    //                 'sampler_jadwal'    => implode(', ', $arrayJadwal)
    //             ];
    //         }
            
    //         $bar->advance();
    //     }

    //     $bar->finish();
    //     $this->newLine(2);

    //     // Pencatatan ke Log Laravel
    //     Log::info('=== AUDIT PERSIAPAN HANTU ===', $kumpulanHantu);
    //     Log::info('=== AUDIT PERSIAPAN TIDAK SINKRON ===', $kumpulanTidakSinkron);

    //     // Cetak hasil di terminal
    //     $this->info("=== HASIL AUDIT ===");
    //     $this->error("Persiapan 'Hantu' ditemukan: " . count($kumpulanHantu) . " record");
    //     $this->warn("Persiapan Tidak Sinkron ditemukan: " . count($kumpulanTidakSinkron) . " record");
    //     $this->info("Detail ID telah disimpan dengan aman di file storage/logs/lumen.log");
    // }

    /**
     * non kontrak
     */
    public function handle()
    {
        $this->info('Memulai audit untuk data non-periode (juli - desember 2025)...');

        // Tentukan rentang tanggal sampling
        $startDate = '2025-07-01';
        $endDate   = '2025-12-31';

        // 1. Tarik Persiapan yang: Aktif, Periode NULL, dan Tanggalnya masuk rentang
        $semuaPersiapanAktif = PersiapanSampelHeader::where('is_active', 1)
            ->whereNull('periode')
            ->whereBetween('tanggal_sampling', [$startDate, $endDate])
            ->get();

        $kumpulanHantu = [];
        $kumpulanTidakSinkron = [];

        $bar = $this->output->createProgressBar(count($semuaPersiapanAktif));
        $bar->start();

        foreach ($semuaPersiapanAktif as $persiapan) {
            // 2. Cari Jadwal yang: Aktif, No Quotation sama, Tanggal sama, dan Periode juga NULL
            $jadwalSamplers = Jadwal::where('no_quotation', $persiapan->no_quotation)
                ->where('tanggal', $persiapan->tanggal_sampling)
                ->where('is_active', 1)
                ->whereNull('periode') // Pastikan mencari yang sama-sama NULL
                ->pluck('sampler')
                ->toArray();

            // LOGIKA 1: JADWAL KOSONG (HANTU)
            if (empty($jadwalSamplers)) {
                $kumpulanHantu[] = [
                    'id_persiapan' => $persiapan->id,
                    'no_document'  => $persiapan->no_document,
                    'no_quotation' => $persiapan->no_quotation,
                    'tanggal'      => $persiapan->tanggal_sampling,
                ];
            } else {
                // LOGIKA 2: TIDAK SINKRON
                $stringSamplerPersiapan = $persiapan->sampler_jadwal ?? '';
                $arrayPersiapan = array_filter(array_map('trim', explode(',', $stringSamplerPersiapan)));
                $arrayJadwal = array_filter(array_map('trim', $jadwalSamplers));

                sort($arrayPersiapan);
                sort($arrayJadwal);

                if ($arrayPersiapan !== $arrayJadwal) {
                    $kumpulanTidakSinkron[] = [
                        'id_persiapan'      => $persiapan->id,
                        'no_quotation'      => $persiapan->no_quotation,
                        'sampler_persiapan' => implode(', ', $arrayPersiapan),
                        'sampler_jadwal'    => implode(', ', $arrayJadwal)
                    ];
                }
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Logging dan Output tetap sama seperti sebelumnya...
        Log::info('=== AUDIT NON-PERIODE (JUL-DEC 2025) HANTU ===', $kumpulanHantu);
        Log::info('=== AUDIT NON-PERIODE (JUL-DEC 2025) TIDAK SINKRON ===', $kumpulanTidakSinkron);

        $this->error("Persiapan 'Hantu' (Non-Periode) ditemukan: " . count($kumpulanHantu));
        $this->warn("Persiapan Tidak Sinkron (Non-Periode) ditemukan: " . count($kumpulanTidakSinkron));
    }

}