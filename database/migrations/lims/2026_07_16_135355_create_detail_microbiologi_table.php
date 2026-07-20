<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailMicrobiologiTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('detail_microbiologi', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->string('parameter', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Kondisi & Metode
            $table->string('kondisi_ruangan', 50)->nullable();
            $table->string('ventilasi', 11)->nullable();
            $table->string('suhu', 10)->nullable();
            $table->string('kelembapan', 10)->nullable();
            $table->string('tekanan_udara', 10)->nullable();
            $table->string('metode_uji', 70)->nullable();
            $table->string('metode_sampling', 70)->nullable();
            
            // Alat & Waktu
            $table->string('nama_alat', 70)->nullable();
            $table->string('nama_alat_manual', 70)->nullable();
            $table->string('waktu_pengukuran', 20)->nullable();
            $table->string('shift_pengambilan', 50)->nullable();
            $table->text('pengukuran')->nullable();
            $table->text('catatan_sampling')->nullable();
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            // Workflow Status & Audit Trails
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('permission')->default(0);
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_active')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_microbiologi');
    }
}
