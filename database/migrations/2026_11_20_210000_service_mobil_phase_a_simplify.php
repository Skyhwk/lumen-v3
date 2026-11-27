<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase A: kurangi duplikasi kolom di service_mobil (estimasi awal → log, snapshot merk/tipe → join master, audit event di log).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        if (Schema::hasColumn('service_mobil', 'tanggal_selesai_rencana_awal')) {
            $rows = DB::table('service_mobil')
                ->select('id', 'tanggal_selesai_rencana_awal', 'tanggal_selesai_estimasi')
                ->whereNotNull('tanggal_selesai_rencana_awal')
                ->get();

            foreach ($rows as $row) {
                $log = DB::table('service_mobil_log')
                    ->where('service_mobil_id', $row->id)
                    ->where('jenis_tindakan', 'dibuat')
                    ->orderBy('id')
                    ->first();

                $baseline = $row->tanggal_selesai_rencana_awal;

                if ($log) {
                    $after = json_decode($log->nilai_sesudah ?? 'null', true);
                    if (!is_array($after)) {
                        $after = [];
                    }
                    if (!array_key_exists('tanggal_selesai_rencana_awal', $after)) {
                        $after['tanggal_selesai_rencana_awal'] = $baseline;
                        $after['tanggal_selesai_estimasi_awal'] = $baseline;
                    }
                    DB::table('service_mobil_log')->where('id', $log->id)->update([
                        'nilai_sesudah' => json_encode($after, JSON_UNESCAPED_UNICODE),
                    ]);
                } else {
                    DB::table('service_mobil_log')->insert([
                        'service_mobil_id' => $row->id,
                        'jenis_tindakan' => 'estimasi_awal_arsip',
                        'nilai_sebelum' => null,
                        'nilai_sesudah' => json_encode([
                            'tanggal_selesai_rencana_awal' => $baseline,
                            'tanggal_selesai_estimasi' => $row->tanggal_selesai_estimasi,
                        ], JSON_UNESCAPED_UNICODE),
                        'alasan' => 'Backfill migrasi Fase A',
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        Schema::table('service_mobil', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('service_mobil', 'tanggal_selesai_rencana_awal')) {
                $cols[] = 'tanggal_selesai_rencana_awal';
            }
            if (Schema::hasColumn('service_mobil', 'merk_mobil')) {
                $cols[] = 'merk_mobil';
            }
            if (Schema::hasColumn('service_mobil', 'tipe_mobil')) {
                $cols[] = 'tipe_mobil';
            }
            foreach (['started_at', 'started_by', 'completed_at', 'completed_by', 'canceled_at', 'canceled_by'] as $col) {
                if (Schema::hasColumn('service_mobil', $col)) {
                    $cols[] = $col;
                }
            }
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });

        try {
            Schema::table('service_mobil', function (Blueprint $table) {
                $table->dropIndex(['plat_mobil']);
            });
        } catch (\Throwable $e) {
            // Index bisa tidak ada di environment tertentu
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('service_mobil')) {
            return;
        }

        Schema::table('service_mobil', function (Blueprint $table) {
            if (!Schema::hasColumn('service_mobil', 'tanggal_selesai_rencana_awal')) {
                $table->date('tanggal_selesai_rencana_awal')->nullable()->after('tanggal_mulai_rencana');
            }
            if (!Schema::hasColumn('service_mobil', 'merk_mobil')) {
                $table->string('merk_mobil', 100)->nullable()->after('plat_mobil');
            }
            if (!Schema::hasColumn('service_mobil', 'tipe_mobil')) {
                $table->string('tipe_mobil', 100)->nullable()->after('merk_mobil');
            }
            if (!Schema::hasColumn('service_mobil', 'started_at')) {
                $table->dateTime('started_at')->nullable();
            }
            if (!Schema::hasColumn('service_mobil', 'started_by')) {
                $table->string('started_by', 255)->nullable();
            }
            if (!Schema::hasColumn('service_mobil', 'completed_at')) {
                $table->dateTime('completed_at')->nullable();
            }
            if (!Schema::hasColumn('service_mobil', 'completed_by')) {
                $table->string('completed_by', 255)->nullable();
            }
            if (!Schema::hasColumn('service_mobil', 'canceled_at')) {
                $table->dateTime('canceled_at')->nullable();
            }
            if (!Schema::hasColumn('service_mobil', 'canceled_by')) {
                $table->string('canceled_by', 255)->nullable();
            }
        });

        DB::statement('UPDATE service_mobil SET tanggal_selesai_rencana_awal = tanggal_selesai_estimasi WHERE tanggal_selesai_rencana_awal IS NULL');

        Schema::table('service_mobil', function (Blueprint $table) {
            $table->index('plat_mobil');
        });
    }
};