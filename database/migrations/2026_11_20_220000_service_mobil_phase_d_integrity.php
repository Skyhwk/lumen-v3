<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase D: FK, index overlap, view blokir.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        if (Schema::hasTable('daftar_mobil')) {
            $validMobilIds = DB::table('daftar_mobil')->pluck('id');
            if ($validMobilIds->isNotEmpty()) {
                DB::table('service_mobil')
                    ->whereNotIn('daftar_mobil_id', $validMobilIds)
                    ->update(['is_active' => false]);
            }
        }

        $hasFk = collect(DB::select("
            SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'service_mobil'
              AND COLUMN_NAME = 'daftar_mobil_id'
              AND REFERENCED_TABLE_NAME = 'daftar_mobil'
            LIMIT 1
        "))->isNotEmpty();

        if (!$hasFk && Schema::hasTable('daftar_mobil')) {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->foreign('daftar_mobil_id', 'service_mobil_daftar_mobil_fk')
                    ->references('id')
                    ->on('daftar_mobil');
            });
        }

        Schema::table('service_mobil', function (Blueprint $table) {
            $table->index(
                ['daftar_mobil_id', 'is_active', 'status'],
                'service_mobil_mobil_active_status_idx'
            );
        });

        DB::statement('DROP VIEW IF EXISTS v_service_mobil_blokir');

        DB::statement("
            CREATE VIEW v_service_mobil_blokir AS
            SELECT
                id,
                daftar_mobil_id,
                plat_mobil,
                status,
                COALESCE(tanggal_mulai_aktual, tanggal_mulai_rencana) AS blok_mulai,
                COALESCE(tanggal_selesai_aktual, tanggal_selesai_estimasi) AS blok_selesai
            FROM service_mobil
            WHERE is_active = 1
              AND status IN ('dijadwalkan', 'dalam_perbaikan')
        ");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_service_mobil_blokir');

        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        Schema::table('service_mobil', function (Blueprint $table) {
            $table->dropIndex('service_mobil_mobil_active_status_idx');
        });

        try {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->dropForeign('service_mobil_daftar_mobil_fk');
            });
        } catch (\Throwable $e) {
            // skip
        }
    }
};