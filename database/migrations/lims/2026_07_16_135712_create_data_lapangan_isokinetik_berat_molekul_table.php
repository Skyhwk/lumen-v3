<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIsokinetikBeratMolekulTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_isokinetik_berat_molekul', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->json('no_sampel_lama')->nullable();
            $table->string('id_lapangan', 7)->nullable();
            
            // Data Parameter & Molekul
            $table->string('diameter', 11)->nullable();
            $table->string('waktu', 11)->nullable();
            $table->string('suhu_cerobong', 11)->nullable();
            $table->string('O2', 20)->nullable();
            $table->string('CO', 20)->nullable();
            $table->string('CO2', 20)->nullable();
            $table->string('NO', 20)->nullable();
            $table->string('NOx', 20)->nullable();
            $table->string('NO2', 20)->nullable();
            $table->string('SO2', 20)->nullable();
            $table->string('O2Mole', 20)->nullable();
            $table->string('CO2Mole', 20)->nullable();
            $table->string('COMole', 20)->nullable();
            $table->string('Ts', 20)->nullable();
            $table->string('N2Mole', 20)->nullable();
            $table->string('MdMole', 20)->nullable();
            $table->string('nCO2', 20)->nullable();
            $table->string('combustion', 20)->nullable();
            $table->string('shift', 10)->nullable();
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
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
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dliskbm_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_isokinetik_berat_molekul');
    }
}
