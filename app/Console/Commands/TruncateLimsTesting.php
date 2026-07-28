<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

// Hanya panggil model yang dibutuhkan
use App\Models\Lims\OrderHeader as LimsOrderHeader;
use App\Models\Lims\OrderDetail as LimsOrderDetail;

class TruncateLimsTesting extends Command
{
    protected $signature = 'lims:truncate-testing';
    protected $description = 'MENGHAPUS data Order Header & Detail di DB LIMS (Hanya untuk Testing)';

    public function handle()
    {
        // 1. Pengaman: Jangan biarkan ini jalan di server Production
        if (App::environment('production')) {
            $this->error('BAHAYA: Command ini tidak boleh dijalankan di environment Production!');
            return 1;
        }

        $this->warn('PERINGATAN: Ini akan menghapus SEMUA data Order Header & Detail di database LIMS.');
        
        // 2. Konfirmasi Ganda Interaktif
        if (!$this->confirm('Apakah Anda sangat yakin ingin mengosongkan (truncate) tabel-tabel ini?')) {
            $this->info('Aksi dibatalkan. Data aman.');
            return 0;
        }

        $this->info('Memulai Truncate...');

        // 3. Matikan Foreign Key Check sementara
        DB::connection('lims')->statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            // Urutan truncate dibalik dari anak (Detail) ke induk (Header)
            LimsOrderDetail::truncate();
            $this->line('- Tabel OrderDetail dikosongkan.');

            LimsOrderHeader::truncate();
            $this->line('- Tabel OrderHeader dikosongkan.');

            $this->info('Berhasil! Semua tabel LIMS untuk testing telah dikosongkan.');

        } catch (\Exception $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
        } finally {
            // 4. Nyalakan kembali Foreign Key Check (WAJIB)
            DB::connection('lims')->statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        return 0;
    }
}