<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganSampahTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_sampah', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 50)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('informasi_tambahan')->nullable();
            
            // Koordinat Geografis & Mata Angin
            $table->string('latitude', 50)->nullable();
            $table->string('longitude', 50)->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            $table->string('arah_utara', 100)->nullable();
            $table->string('arah_timur_laut', 100)->nullable();
            $table->string('arah_timur', 100)->nullable();
            $table->string('arah_tenggara', 100)->nullable();
            $table->string('arah_selatan', 100)->nullable();
            $table->string('arah_barat_daya', 100)->nullable();
            $table->string('arah_barat', 100)->nullable();
            $table->string('arah_barat_laut', 100)->nullable();
            $table->string('waktu_pengambilan', 100)->nullable();
            $table->text('catatan_sampler')->nullable();
            
            // Dokumentasi Lokasi Sampling
            $table->text('foto_lokasi_selatan')->nullable();
            $table->text('foto_lokasi_utara')->nullable();
            $table->text('foto_lokasi_timur')->nullable();
            $table->text('foto_lokasi_barat')->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('permission')->default(0);
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('blocked_by', 100)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->integer('is_blocked')->default(0);
            $table->string('rejected_by', 100)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->integer('is_rejected')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_sampah');
    }
}
