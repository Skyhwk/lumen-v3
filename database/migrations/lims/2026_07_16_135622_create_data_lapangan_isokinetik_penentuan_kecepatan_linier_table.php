<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIsokinetikPenentuanKecepatanLinierTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_isokinetik_penentuan_kecepatan_linier', function (Blueprint $table) {
            $table->id();
            $table->string('no_survei', 20)->nullable();
            $table->string('no_sampel', 20)->nullable();
            $table->json('no_sampel_lama')->nullable();
            $table->string('id_lapangan', 7)->nullable();
            
            // Parameter Cerobong & Pengukuran
            $table->string('diameter_cerobong', 11)->nullable();
            $table->string('suhu', 11)->nullable();
            $table->string('kelembapan', 11)->nullable();
            $table->string('tekanan_udara', 11)->nullable();
            $table->string('kp', 11)->nullable();
            $table->string('cp', 11)->nullable();
            $table->integer('tekPa')->nullable();
            $table->string('waktu_pengukuran', 11)->nullable();
            $table->text('dataDp')->nullable();
            $table->string('dP', 11)->nullable();
            $table->string('rerata_suhu', 10)->nullable();
            $table->string('rerata_paps', 10)->nullable();
            $table->string('TM', 11)->nullable();
            $table->string('Ps', 11)->nullable();
            $table->string('kecLinier', 30)->nullable();
            
            // Mutu & Status
            $table->json('jaminan_mutu')->nullable();
            $table->string('status_test', 15)->nullable();
            $table->json('uji_aliran')->nullable();
            
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
            $table->index('no_sampel', 'idx_dliskpkl_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_isokinetik_penentuan_kecepatan_linier');
    }
}
