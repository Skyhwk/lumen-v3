<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDetailLingkunganKerjaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('detail_lingkungan_kerja', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Koordinat & Detail Lokasi
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi', 11)->nullable();
            
            // Kondisi Lingkungan Kerja
            $table->string('laju_ventilasi', 11)->nullable();
            $table->string('kecepatan_angin', 10)->nullable();
            $table->string('intensitas', 18)->nullable();
            $table->text('aktifitas')->nullable();
            $table->string('cuaca', 11)->nullable();
            $table->string('jarak_sumber_cemaran', 10)->nullable();
            $table->string('suhu', 10)->nullable();
            $table->string('kelembapan', 10)->nullable();
            $table->string('tekanan_udara', 10)->nullable();
            $table->text('deskripsi_bau')->nullable();
            
            // Pengukuran
            $table->string('waktu_pengukuran', 20)->nullable();
            $table->string('kategori_pengujian', 50)->nullable();
            $table->string('shift_pengambilan', 10)->nullable();
            $table->string('metode_pengukuran', 20)->nullable();
            $table->string('parameter', 40)->nullable();
            $table->string('satuan', 8)->nullable();
            $table->text('pengukuran')->nullable();
            $table->text('absorbansi')->nullable();
            $table->text('catatan_kondisi_lapangan')->nullable();
            $table->string('durasi_pengujian', 70)->nullable();
            
            // Dokumentasi
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            // Workflow Status & Audit Trails
            $table->integer('permission')->default(0);
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_by', 150)->nullable();
            $table->timestamp('rejected_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_lingkungan_kerja');
    }
}
