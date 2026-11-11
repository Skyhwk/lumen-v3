<?php

namespace Database\Seeders;

use App\Models\MasterWilayahK3;
use Illuminate\Database\Seeder;

class MasterWilayahK3Seeder extends Seeder
{
    public function run(): void
    {
        $wilayahPelanggan = [
            'Wilayah Cilegon',
            'Wilayah Serang',
            'Wilayah Tangerang',
            'Wilayah Jakarta',
            'Wilayah Jawa Barat I (Bogor, Sukabumi)',
            'Wilayah Jawa Barat II (Bekasi, Cikarang, Karawang, Purwakarta)',
            'Wilayah Jawa Barat III (Cirebon, Kuningan, Majalengka, Indramayu)',
            'Wilayah Jawa Barat IV (Bandung, Tasikmalaya)',
            'Wilayah Jawa Tengah',
            'Wilayah Jawa Timur',
            'Wilayah Kalimantan',
            'Wilayah Sumatera Selatan',
            'Wilayah Sumatera Utara',
            'Wilayah Riau',
            'Wilayah Kepulauan Riau',
        ];

        $timestamp = date('Y-m-d H:i:s');

        foreach ($wilayahPelanggan as $nama) {
            $exists = MasterWilayahK3::where('wilayah_pelanggan', $nama)
                ->where('is_active', true)
                ->exists();

            if ($exists) {
                continue;
            }

            MasterWilayahK3::create([
                'wilayah_pelanggan' => $nama,
                'id_cabang' => null,
                'is_active' => true,
                'created_by' => 'system',
                'created_at' => $timestamp,
            ]);
        }
    }
}
