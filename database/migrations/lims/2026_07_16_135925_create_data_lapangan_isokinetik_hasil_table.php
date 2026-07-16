<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIsokinetikHasilTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_isokinetik_hasil', function (Blueprint $table) {
            $table->increments('id'); // int AUTO_INCREMENT
            $table->integer('id_lapangan')->nullable();
            $table->string('no_sampel', 30)->nullable();
            $table->json('no_sampel_lama')->nullable();
            
            // Data Kalkulasi Impinger & Volume
            $table->string('impinger1', 15)->nullable();
            $table->string('impinger2', 15)->nullable();
            $table->string('impinger3', 20)->nullable();
            $table->string('impinger4', 20)->nullable();
            $table->string('totalBobot', 20)->nullable();
            $table->string('Collector', 20)->nullable();
            $table->string('v_wtr', 20)->nullable();
            $table->string('v_gas', 20)->nullable();
            $table->string('gas_vol', 20)->nullable();
            $table->string('bws_frac', 50)->nullable();
            $table->string('bws_aktual', 20)->nullable();
            $table->string('ps', 20)->nullable();
            $table->string('avgVs', 20)->nullable();
            $table->string('recoveryacetone', 20)->nullable();
            $table->string('qs', 20)->nullable();
            $table->string('qs_act', 20)->nullable();
            $table->string('avg_Tm', 20)->nullable();
            $table->string('avgTS', 20)->nullable();
            $table->string('persenIso', 20)->nullable();
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 30)->nullable();
            $table->string('foto_kondisi_sampel', 30)->nullable();
            $table->string('foto_lain', 30)->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->text('approved_at')->nullable(); // DDL menetapkan ini text
            $table->integer('permission')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dliskh_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_isokinetik_hasil');
    }
}
