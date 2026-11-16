<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            if (!Schema::hasColumn('observasi_k3', 'jabatan_petugas')) {
                $table->string('jabatan_petugas')->nullable()->after('petugas_observasi');
            }
            if (!Schema::hasColumn('observasi_k3', 'nama_mengetahui')) {
                $table->string('nama_mengetahui')->nullable()->after('jabatan_petugas');
            }
            if (!Schema::hasColumn('observasi_k3', 'tanggal_pengesahan')) {
                $table->date('tanggal_pengesahan')->nullable()->after('nama_mengetahui');
            }
            if (!Schema::hasColumn('observasi_k3', 'ttd_petugas')) {
                $table->string('ttd_petugas')->nullable()->after('tanggal_pengesahan');
            }
            if (!Schema::hasColumn('observasi_k3', 'ttd_mengetahui')) {
                $table->string('ttd_mengetahui')->nullable()->after('ttd_petugas');
            }
            if (!Schema::hasColumn('observasi_k3', 'snapshot_pengesahan')) {
                $table->json('snapshot_pengesahan')->nullable()->after('form_jawaban');
            }
            if (!Schema::hasColumn('observasi_k3', 'is_pengesahan_selesai')) {
                $table->boolean('is_pengesahan_selesai')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            foreach ([
                'is_pengesahan_selesai',
                'snapshot_pengesahan',
                'ttd_mengetahui',
                'ttd_petugas',
                'tanggal_pengesahan',
                'nama_mengetahui',
                'jabatan_petugas',
            ] as $col) {
                if (Schema::hasColumn('observasi_k3', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
