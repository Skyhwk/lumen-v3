<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganPartikulatMeterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_partikulat_meter', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Lokasi & Koordinat
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('parameter', 40)->nullable();
            $table->string('lokasi', 30)->nullable();
            
            // Waktu & Metode Pengujian
            $table->string('waktu_pengukuran', 20)->nullable();
            $table->string('kategori_pengujian', 30)->nullable();
            $table->string('shift_pengambilan', 20)->nullable();
            
            // Metrik Lingkungan & ISO
            $table->string('suhu', 5)->nullable();
            $table->string('kelembapan', 5)->nullable();
            $table->string('tekanan_udara', 50)->nullable();
            $table->string('kelas_iso', 10)->nullable();
            $table->string('flow', 10)->nullable();
            $table->string('nilai_iso', 10)->nullable();
            
            // Dimensi Area & Pengukuran
            $table->string('panjang', 10)->nullable();
            $table->string('lebar', 10)->nullable();
            $table->string('luas_area', 10)->nullable();
            $table->string('jumlah_titik', 10)->nullable();
            $table->string('pengukuran', 200)->nullable();
            $table->json('pengukuran_baru')->nullable();
            $table->text('catatan_sampler')->nullable();
            
            // Berkas Gambar
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
            $table->index('no_sampel', 'idx_dlpm_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_partikulat_meter');
    }
}
