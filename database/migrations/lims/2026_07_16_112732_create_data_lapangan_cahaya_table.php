<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganCahayaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_cahaya', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->string('kategori', 30)->nullable();
            $table->text('keterangan')->nullable();
            $table->text('informasi_tambahan')->nullable();
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('panjang', 15)->nullable();
            $table->string('lebar', 15)->nullable();
            $table->string('luas', 15)->nullable();
            $table->integer('jumlah_titik_pengujian')->nullable();
            $table->integer('titik_pengujian_sampler')->nullable();
            $table->string('jenis_tempat_alat_sensor', 60)->nullable();
            $table->string('jenis_cahaya', 50)->nullable();
            $table->string('jenis_lampu', 50)->nullable();
            $table->integer('jumlah_tenaga_kerja')->nullable();
            $table->text('aktifitas')->nullable();
            $table->string('jam_mulai_pengukuran', 50)->nullable();
            $table->string('waktu_pengambilan', 30)->nullable();
            $table->text('pengukuran')->nullable();
            $table->text('nilai_pengukuran')->nullable();
            $table->string('jam_selesai_pengukuran', 50)->nullable();
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            $table->integer('permission')->default(0);
            
            // Audit Trails & Approval
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->tinyInteger('is_rejected')->default(0);
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing sesuai DDL
            $table->index('no_sampel', 'idx_dlc_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_cahaya');
    }
}
