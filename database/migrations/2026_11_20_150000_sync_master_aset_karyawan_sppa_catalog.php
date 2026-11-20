<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Samakan master aset karyawan dengan daftar SPPA (PDF penerimaan aset TC / Analyst / Sampling).
 * ID Card tetap dikelola sistem (bukan bagian katalog dropdown).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const CATALOG = [
        'Jas Laboratorium',
        'Kacamata Googles Lab',
        'Kacamata Medicon',
        'Respirator',
        'Catridge Respirator',
        'Filter Catridge Respirator',
        'Earplugg',
        'Earmuff',
        'Sarung Tangan Bintik Kuning',
        'Helm Safety',
        'Sepatu Safety',
        'Baju Sampling',
        'Tas Sampling',
        'Handphone',
        'Handphone Sampling',
    ];

    /** @var list<string> */
    private const LEGACY_TO_DEACTIVATE = [
        'Laptop',
        'Mouse',
        'Keyboard',
        'Seragam',
        'Tas Kerja',
        'Handphone Dinas',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('master_aset_karyawan')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        foreach (self::CATALOG as $nama) {
            $exists = DB::table('master_aset_karyawan')
                ->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($nama))])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('master_aset_karyawan')->insert([
                'nama' => $nama,
                'created_at' => $now,
                'created_by' => 'system:sppa-catalog',
                'is_active' => 1,
            ]);
        }

        foreach (self::LEGACY_TO_DEACTIVATE as $nama) {
            $rows = DB::table('master_aset_karyawan')
                ->whereRaw('LOWER(TRIM(nama)) = ?', [strtolower(trim($nama))])
                ->where('is_active', true)
                ->get(['id']);

            foreach ($rows as $row) {
                $inUse = Schema::hasTable('aset_karyawan') && DB::table('aset_karyawan')
                    ->where('master_aset_karyawan_id', $row->id)
                    ->where('is_active', true)
                    ->exists();

                if ($inUse) {
                    continue;
                }

                DB::table('master_aset_karyawan')->where('id', $row->id)->update([
                    'is_active' => 0,
                    'deleted_at' => $now,
                    'deleted_by' => 'system:sppa-catalog',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data master tidak di-rollback otomatis (hindari hapus referensi produksi).
    }
};