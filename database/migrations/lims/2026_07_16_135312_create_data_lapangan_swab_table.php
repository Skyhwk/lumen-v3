<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganSwabTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_swab', function (Blueprint $table) {
            $table->id(); // bigint
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            $table->text('kondisi_tempat_sampling')->nullable();
            $table->string('kondisi_sampel', 15)->nullable();
            
            // Pengukuran Lingkungan
            $table->string('waktu_pengukuran', 20)->nullable();
            $table->string('suhu', 10)->nullable();
            $table->string('kelembapan', 10)->nullable();
            $table->string('tekanan_udara', 10)->nullable();
            $table->string('luas_area_swab', 10)->nullable();
            $table->text('catatan')->nullable();
            
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
            $table->tinyInteger('is_rejected')->default(0);
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlsb_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_swab');
    }
}
