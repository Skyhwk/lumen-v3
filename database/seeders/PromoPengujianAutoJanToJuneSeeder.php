<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromoPengujianAutoJanToJuneSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('promo_pengujian')) {
            if ($this->command) {
                $this->command->warn('Tabel promo_pengujian belum ada. Jalankan migration dulu.');
            }
            return;
        }

        $now = Carbon::now();
        $kodePromo = 'Auto_Jan_to_June';
        $payload = [
            'metode' => 'labeling',
            'nama_diskon' => 'Auto Januari sampai Juni',
            'konfigurasi' => json_encode([]),
            'status' => 'aktif',
            'updated_by' => 'seeder',
            'updated_at' => $now,
        ];

        if (DB::table('promo_pengujian')->where('kode_promo', $kodePromo)->exists()) {
            if ($this->command) {
                $this->command->line('Promo pengujian ' . $kodePromo . ' sudah ada, seeder dilewati.');
            }
            return;
        }

        $payload['kode_promo'] = $kodePromo;
        $payload['created_by'] = 'seeder';
        $payload['created_at'] = $now;
        DB::table('promo_pengujian')->insert($payload);

        if ($this->command) {
            $this->command->info('Promo pengujian ' . $kodePromo . ' ditambahkan (labeling, aktif).');
        }
    }
}
