<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganMedanLmTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_medan_lm', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->string('kategori_3', 70)->nullable();
            $table->string('parameter', 30)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Lokasi & Geospasial
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi', 30)->nullable();
            
            // Kondisi Kerja & Pemaparan
            $table->text('aktivitas_pekerja')->nullable();
            $table->string('sumber_radiasi', 50)->nullable();
            $table->string('waktu_pemaparan', 10)->nullable();
            $table->string('waktu_pengukuran', 20)->nullable();
            
            // Parameter Medan Magnet
            $table->string('magnet_3', 100)->nullable();
            $table->string('magnet_30', 100)->nullable();
            $table->string('magnet_100', 100)->nullable();
            
            // Parameter Medan Listrik
            $table->string('listrik_3', 100)->nullable();
            $table->string('listrik_30', 100)->nullable();
            $table->string('listrik_100', 100)->nullable();
            
            // Parameter Frekuensi
            $table->string('frekuensi_3', 70)->nullable();
            $table->string('frekuensi_30', 70)->nullable();
            $table->string('frekuensi_100', 70)->nullable();
            
            // Media Gambar
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
            $table->index('no_sampel', 'idx_dlm_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_medan_lm');
    }
}
