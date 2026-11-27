<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jadwal_mobilisasi')) {
            return;
        }

        Schema::create('jadwal_mobilisasi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('id_sampling')->nullable();
            $table->string('no_quotation', 100)->nullable();
            $table->date('tanggal')->nullable();
            $table->string('warna', 50)->nullable();

            // Relasi ke group jadwal: menyimpan CSV batch_id (misal "101,102,103") & array JSON [101,102,103]
            $table->text('batch_id');
            $table->json('batch_jadwal_ids')->nullable();

            // 1. Tujuan Sampling
            $table->string('tujuan_sampling', 255)->nullable();
            $table->string('tujuan_sampling_pilihan', 150)->nullable();
            $table->string('tujuan_sampling_manual', 255)->nullable();

            // 2. Estimasi Perjalanan (Jarak Tempuh)
            $table->unsignedSmallInteger('estimasi_jam')->default(0);
            $table->unsignedSmallInteger('estimasi_menit')->default(0);
            $table->string('waktu_berangkat', 100)->nullable()->comment('Contoh: 1 Jam 30 Menit');
            $table->string('keterangan_akomodasi', 255)->nullable()->comment('Contoh: PT INTI SURYA LABORATORIUM');
            $table->string('rute_perjalanan', 50)->nullable()->comment('TOLL / NON TOLL / TOLL & NON TOLL');
            $table->string('estimasi_perjalanan', 500)->nullable()->comment('Contoh: 1 Jam 30 Menit dari PT INTI SURYA LABORATORIUM & RUTE (TOLL)');

            // 3 & 4. Kebutuhan Peralatan Sampling (JSON array: [{"nama_alat": "Toolbox", "jumlah_alat": 2}, ...])
            $table->json('peralatan_sampling')->nullable();

            $table->boolean('is_active')->default(true);
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['id_sampling', 'tanggal'], 'idx_jm_sampling_tanggal');
            $table->index(['tanggal', 'warna'], 'idx_jm_tanggal_warna');
            $table->index('no_quotation', 'idx_jm_no_quotation');
            $table->index('is_active', 'idx_jm_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_mobilisasi');
    }
};
