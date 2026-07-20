<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganEmisiKendaraanTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_emisi_kendaraan', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 70)->nullable();
            $table->text('no_sampel_lama')->nullable();
            
            // Raw/Text Data Hasil Pengujian
            $table->string('data_co', 75)->nullable();
            $table->string('data_co2', 75)->nullable();
            $table->string('data_hc', 75)->nullable();
            $table->string('data_o2', 75)->nullable();
            $table->string('data_opasitas', 75)->nullable();
            
            // Metrik Spesifik Kendaraan & Emisi
            $table->string('km', 100)->nullable();
            $table->string('co2', 10)->nullable();
            $table->string('co', 10)->nullable();
            $table->string('hc', 10)->nullable();
            $table->string('o2', 10)->nullable();
            $table->string('lamda', 10)->nullable();
            $table->string('nilai_km', 10)->nullable();
            $table->string('rpm', 10)->nullable();
            $table->string('suhu_oli', 10)->nullable();
            $table->string('opasitas', 10)->nullable();
            
            // Dokumentasi
            $table->string('foto_depan', 40)->nullable();
            $table->string('foto_belakang', 40)->nullable();
            $table->string('foto_sampling', 40)->nullable();
            
            // System Control & Audit Trails
            $table->integer('is_active')->default(1);
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('is_blocked')->default(0);
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('deleted_by', 150)->nullable();
            $table->timestamp('deleted_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlk_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_emisi_kendaraan');
    }
}
