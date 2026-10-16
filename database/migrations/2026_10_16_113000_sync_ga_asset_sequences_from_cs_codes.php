<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_asset_sequences') || !Schema::hasTable('master_sub_kategori_aset')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $subs = DB::table('master_sub_kategori_aset')->get(['id', 'nama_sub_kategori']);

        foreach ($subs as $sub) {
            $name = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $sub->nama_sub_kategori)));
            if ($name === '') {
                $name = 'ASET';
            }

            $existingMax = (int) DB::table('ga_assets')
                ->where('cs_code', 'like', 'CS-' . $name . '-%')
                ->max(DB::raw("CAST(SUBSTRING_INDEX(cs_code, '-', -1) AS UNSIGNED)"));

            DB::table('ga_asset_sequences')->insertOrIgnore([
                'sub_kategori_aset_id' => $sub->id,
                'last_number' => $existingMax,
                'updated_at' => $now,
            ]);

            DB::table('ga_asset_sequences')
                ->where('sub_kategori_aset_id', $sub->id)
                ->where('last_number', '<', $existingMax)
                ->update([
                    'last_number' => $existingMax,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
    }
};
