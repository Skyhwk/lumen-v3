<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_harga_parameter') || !Schema::hasTable('konfigurasi_label_parameter')) {
            return;
        }

        $hasVolume = Schema::hasColumn('master_harga_parameter', 'volume');
        $hasRegen = Schema::hasColumn('master_harga_parameter', 'regen');

        if ($hasVolume || $hasRegen) {
            $this->copyRemainingRows();
        }

        Schema::table('master_harga_parameter', function (Blueprint $table) use ($hasVolume, $hasRegen) {
            if ($hasVolume) {
                $table->dropColumn('volume');
            }
            if ($hasRegen) {
                $table->dropColumn('regen');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('master_harga_parameter')) {
            return;
        }

        Schema::table('master_harga_parameter', function (Blueprint $table) {
            if (!Schema::hasColumn('master_harga_parameter', 'volume')) {
                $table->string('volume', 50)->nullable();
            }
            if (!Schema::hasColumn('master_harga_parameter', 'regen')) {
                $table->string('regen', 255)->nullable();
            }
        });
    }

    private function copyRemainingRows(): void
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $query = DB::table('master_harga_parameter')
            ->where('is_active', true);

        if (Schema::hasColumn('master_harga_parameter', 'status')) {
            $query->where(function ($q) {
                $q->where('status', 0)->orWhereNull('status');
            });
        }

        $pickedIds = (clone $query)
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

        foreach ($rows as $row) {
            $hasValue = ($row->volume !== null && $row->volume !== '')
                || ($row->regen !== null && $row->regen !== '');

            if (!$hasValue) {
                continue;
            }

            $existing = DB::table('konfigurasi_label_parameter')
                ->where('id_parameter', $row->id_parameter)
                ->first();

            if ($existing) {
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
};
