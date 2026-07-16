<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIklimDinginTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_iklim_dingin', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->string('keterangan', 50)->nullable();
            $table->string('keterangan_2', 100)->nullable();
            
            // Lokasi & Instrumentasi
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi', 30)->nullable();
            $table->string('tipe_alat', 50)->nullable();
            $table->string('sumber_dingin', 100)->nullable();
            $table->integer('jarak_sumber_dingin')->nullable();
            
            // Eksposur & Aktivitas
            $table->string('akumulasi_waktu_paparan', 25)->nullable();
            $table->string('waktu_kerja', 25)->nullable();
            $table->string('apd_khusus', 15)->nullable();
            $table->string('aktifitas', 20)->nullable();
            $table->text('aktifitas_kerja')->nullable();
            
            // Metrik Pengujian & Waktu
            $table->string('jam_awal_pengukuran', 25)->nullable();
            $table->string('kategori_pengujian', 20)->nullable();
            $table->string('shift_pengambilan', 30)->nullable();
            $table->text('pengukuran')->nullable();
            $table->string('jam_akhir_pengujian', 25)->nullable();
            
            // Media Dokumentasi
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            // Workflow Status & Audit Trails
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('permission')->default(0);
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->tinyInteger('is_rejected')->default(0);
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlik_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_iklim_dingin');
    }
}
