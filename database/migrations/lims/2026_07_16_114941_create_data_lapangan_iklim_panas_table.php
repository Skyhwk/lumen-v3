<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganIklimPanasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_iklim_panas', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->string('keterangan', 100)->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Lokasi & Kondisi Sumber
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi', 30)->nullable();
            $table->string('sumber_panas', 255)->nullable();
            $table->integer('jarak_sumber_panas')->nullable();
            
            // Parameter Waktu & Operasional
            $table->string('akumulasi_waktu_paparan', 25)->nullable();
            $table->string('waktu_kerja', 25)->nullable();
            $table->string('jam_awal_pengukuran', 25)->nullable();
            $table->string('kategori_pengujian', 20)->nullable();
            $table->string('shift_pengujian', 30)->nullable();
            
            // Parameter Suhu Ruang & Kelembapan (In/Out)
            $table->string('tac_in', 10)->nullable();
            $table->string('tac_out', 10)->nullable();
            $table->string('tgc_in', 10)->nullable();
            $table->string('tgc_out', 10)->nullable();
            $table->string('wbtgc_in', 10)->nullable();
            $table->string('wbtgc_out', 10)->nullable();
            $table->string('rh_in', 10)->nullable();
            $table->string('rh_out', 10)->nullable();
            
            // Data Pengukuran Tambahan & Lingkungan
            $table->text('pengukuran')->nullable();
            $table->string('jam_akhir_pengukuran', 25)->nullable();
            $table->string('cuaca', 20)->nullable();
            $table->string('pakaian_yang_digunakan', 255)->nullable();
            $table->string('terpapar_panas_matahari', 20)->nullable();
            $table->string('tipe_alat', 35)->nullable();
            $table->text('aktifitas')->nullable();
            
            // Dokumentasi
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
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 70)->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlikp_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_iklim_panas');
    }
}
