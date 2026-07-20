<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganSinaruvTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_sinaruv', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->string('keterangan', 150)->nullable();
            $table->string('keterangan_2', 100)->nullable();
            
            // Lokasi & Koordinat
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi', 30)->nullable();
            
            // Pengukuran & Paparan
            $table->text('aktivitas_pekerja')->nullable();
            $table->string('sumber_radiasi', 50)->nullable();
            $table->string('waktu_pemaparan', 10)->nullable();
            $table->string('waktu_pengukuran', 20)->nullable();
            
            // Titik Paparan Tubuh
            $table->text('mata')->nullable();
            $table->text('betis')->nullable();
            $table->text('siku')->nullable();
            
            // Dokumentasi & Catatan
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            $table->text('catatan_sampler')->nullable();
            
            // Workflow Status & Audit Trails
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('permission')->default(0);
            $table->integer('is_active')->default(1);
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
            $table->index('no_sampel', 'idx_dlsuv_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_sinaruv');
    }
}
