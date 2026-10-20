<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_asset_cabang_aliases')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['alias' => 'PUSAT', 'id_cabang' => 1],
            ['alias' => 'HEAD OFFICE', 'id_cabang' => 1],
            ['alias' => 'CABANG KARAWANG', 'id_cabang' => 4],
            ['alias' => 'RO-KARAWANG', 'id_cabang' => 4],
            ['alias' => 'KARAWANG', 'id_cabang' => 4],
            ['alias' => 'CABANG PEMALANG', 'id_cabang' => 5],
            ['alias' => 'RO-PEMALANG', 'id_cabang' => 5],
            ['alias' => 'PEMALANG', 'id_cabang' => 5],
            ['alias' => 'CABANG PEMALANG - KARAWANG', 'id_cabang' => 5],
            ['alias' => 'DIREKSI', 'id_cabang' => 1],
            ['alias' => 'GUDANG', 'id_cabang' => 1],
            ['alias' => 'PANTRY', 'id_cabang' => 1],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('ga_asset_cabang_aliases')->where('alias', $row['alias'])->exists();
            if (!$exists) {
                DB::table('ga_asset_cabang_aliases')->insert([
                    'alias' => $row['alias'],
                    'id_cabang' => $row['id_cabang'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ga_asset_cabang_aliases')) {
            DB::table('ga_asset_cabang_aliases')->whereIn('alias', [
                'PUSAT', 'HEAD OFFICE', 'CABANG KARAWANG', 'RO-KARAWANG', 'KARAWANG',
                'CABANG PEMALANG', 'RO-PEMALANG', 'PEMALANG', 'CABANG PEMALANG - KARAWANG',
                'DIREKSI', 'GUDANG', 'PANTRY',
            ])->delete();
        }
    }
};
