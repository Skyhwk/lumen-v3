<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganAirTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_air', function (Blueprint $table) {
            $table->id(); // bigint NOT NULL AUTO_INCREMENT
            
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->string('jenis_sampel', 100)->nullable();
            $table->string('kedalaman_titik', 30)->nullable();
            $table->string('jenis_produksi', 150)->nullable();
            $table->string('lokasi_titik_pengambilan', 30)->nullable();
            $table->text('jenis_fungsi_air')->nullable();
            $table->string('status_kesediaan_ipal', 30)->nullable();
            $table->string('jumlah_titik_pengambilan', 30)->nullable();
            $table->string('lokasi_sampling', 30)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('informasi_tambahan')->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            $table->string('latitude', 100)->nullable();
            $table->string('longitude', 100)->nullable();
            $table->text('diameter_sumur')->nullable();
            $table->string('kedalaman_sumur1', 30)->nullable();
            $table->string('kedalaman_sumur2', 30)->nullable();
            $table->string('kedalaman_air_terambil', 30)->nullable();
            $table->string('total_waktu', 30)->nullable();
            $table->string('teknik_sampling', 30)->nullable();
            $table->string('jam_pengambilan', 30)->nullable();
            $table->string('volume', 30)->nullable();
            $table->text('jenis_pengawet')->nullable();
            $table->string('perlakuan_penyaringan', 30)->nullable();
            $table->string('pengendalian_mutu', 200)->nullable();
            $table->string('teknik_pengukuran_debit', 30)->nullable();
            $table->string('debit_air', 50)->nullable();
            $table->string('do', 30)->nullable();
            $table->string('ph', 30)->nullable();
            $table->string('suhu_air', 30)->nullable();
            $table->string('suhu_udara', 30)->nullable();
            $table->string('dhl', 30)->nullable();
            $table->string('warna', 30)->nullable();
            $table->string('bau', 30)->nullable();
            $table->string('salinitas', 30)->nullable();
            $table->string('kecepatan_arus', 30)->nullable();
            $table->string('arah_arus', 30)->nullable();
            $table->text('pasang_surut')->nullable();
            $table->string('kecerahan', 30)->nullable();
            $table->string('lapisan_minyak', 30)->nullable();
            $table->string('cuaca', 30)->nullable();
            $table->string('sampah', 50)->nullable();
            $table->string('klor_bebas', 30)->nullable();
            $table->string('lokasi_submit', 80)->nullable();
            
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            $table->integer('permission')->nullable();
            
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->integer('is_blocked')->default(0)->nullable();
            
            $table->boolean('is_rejected')->default(0)->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dla_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_air');
    }
}
