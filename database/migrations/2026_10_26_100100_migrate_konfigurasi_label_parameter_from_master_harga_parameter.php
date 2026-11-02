<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('konfigurasi_label_parameter') || !Schema::hasTable('master_harga_parameter')) {
            return;
        }

        $sourceQuery = DB::table('master_harga_parameter')
            ->where('id_kategori', 1)
            ->where('is_active', true)
            ->whereNotNull('regen')
            ->where('regen', '!=', '');

        if (Schema::hasColumn('master_harga_parameter', 'status')) {
            $sourceQuery->where('status', 0);
        }

        $pickedIds = (clone $sourceQuery)
            ->select('id_parameter')
            ->selectRaw('MIN(id) as source_id')
            ->groupBy('id_parameter');

        $rows = DB::table('master_harga_parameter as mhp')
            ->joinSub($pickedIds, 'picked', function ($join) {
                $join->on('mhp.id', '=', 'picked.source_id');
            })
            ->get([
                'mhp.id_parameter',
                'mhp.nama_parameter',
                'mhp.volume',
                'mhp.regen',
                'mhp.created_at',
                'mhp.created_by',
                'mhp.updated_at',
                'mhp.updated_by',
            ]);

        if ($rows->isEmpty()) {
            $rows = $this->fallbackRowsFromMasterHargaParameter();
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $existing = DB::table('konfigurasi_label_parameter')
                ->where('id_parameter', $row->id_parameter)
                ->first();

            if ($existing) {
                DB::table('konfigurasi_label_parameter')
                    ->where('id_parameter', $row->id_parameter)
                    ->update([
                        'nama_parameter' => $row->nama_parameter,
                        'volume' => $row->volume,
                        'regen' => $row->regen,
                        'is_active' => true,
                        'updated_at' => $row->updated_at ?? $now,
                        'updated_by' => $row->updated_by,
                    ]);
                continue;
            }

            DB::table('konfigurasi_label_parameter')->insert([
                'id_parameter' => $row->id_parameter,
                'nama_parameter' => $row->nama_parameter,
                'volume' => $row->volume,
                'regen' => $row->regen,
                'is_active' => true,
                'created_at' => $row->created_at ?? $now,
                'created_by' => $row->created_by,
                'updated_at' => $row->updated_at,
                'updated_by' => $row->updated_by,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('konfigurasi_label_parameter')) {
            return;
        }

        DB::table('konfigurasi_label_parameter')->truncate();
    }

    /**
     * Jika filter status=0 tidak menghasilkan baris, ambil aggregate per id_parameter (logic lama indexAir).
     */
    private function fallbackRowsFromMasterHargaParameter()
    {
        return DB::table('master_harga_parameter')
            ->where('id_kategori', 1)
            ->where('is_active', true)
            ->whereNotNull('regen')
            ->where('regen', '!=', '')
            ->select('id_parameter')
            ->selectRaw('MIN(nama_parameter) as nama_parameter')
            ->selectRaw('MIN(volume) as volume')
            ->selectRaw('MIN(regen) as regen')
            ->selectRaw('MIN(created_at) as created_at')
            ->selectRaw('MIN(created_by) as created_by')
            ->selectRaw('MAX(updated_at) as updated_at')
            ->selectRaw('MAX(updated_by) as updated_by')
            ->groupBy('id_parameter')
            ->get();
    }
};
