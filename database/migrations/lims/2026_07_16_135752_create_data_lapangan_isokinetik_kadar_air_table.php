<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIsokinetikKadarAirTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_isokinetik_kadar_air', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->json('no_sampel_lama')->nullable();
            $table->string('id_lapangan', 7)->nullable();
            $table->string('waktu', 11)->nullable();
            $table->string('diameter_cerobong', 15)->nullable();
            $table->string('metode_uji', 50)->nullable();
            
            // Pengukuran Kadar Air
            $table->string('kadar_air', 7)->nullable();
            $table->string('laju_aliran', 7)->nullable();
            $table->json('data_impinger')->nullable();
            $table->string('nilai_y', 9)->nullable();
            $table->string('Pm', 9)->nullable();
            $table->string('suhu_cerobong', 8)->nullable();
            $table->json('data_dgmterbaca')->nullable();
            $table->json('data_kalkulasi_dgm')->nullable();
            
            // Mutu & Hasil Akhir
            $table->string('jaminan_mutu', 50)->nullable();
            $table->json('data_dgm_test')->nullable();
            $table->string('dgm_test', 15)->nullable();
            $table->string('waktu_test', 15)->nullable();
            $table->string('laju_alir_test', 15)->nullable();
            $table->string('tekV_test', 15)->nullable();
            $table->string('hasil_test', 15)->nullable();
            $table->string('vwc', 15)->nullable();
            $table->string('vmstd', 15)->nullable();
            $table->string('vwsg', 15)->nullable();
            $table->string('bws', 15)->nullable();
            $table->string('ms', 15)->nullable();
            $table->string('vs', 15)->nullable();
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
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
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dliskka_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_isokinetik_kadar_air');
    }
}
