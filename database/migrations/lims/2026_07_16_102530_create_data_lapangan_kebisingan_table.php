<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganKebisinganTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_kebisingan', function (Blueprint $table) {
            $table->id();
            
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('informasi_tambahan')->nullable();
            $table->time('waktu')->nullable();
            $table->string('titik_koordinat', 100)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('lokasi_titik_sampling', 30)->nullable();
            $table->text('sumber_kebisingan')->nullable();
            $table->string('jenis_frekuensi_kebisingan', 100)->nullable();
            $table->string('jenis_kategori_kebisingan', 100)->nullable();
            $table->string('jenis_durasi_sampling', 100)->nullable();
            $table->text('value_kebisingan')->nullable();
            $table->string('jam_pemaparan', 10)->nullable();
            $table->string('suhu_udara', 30)->nullable();
            $table->string('kelembapan_udara', 30)->nullable();
            
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            $table->integer('permission')->default(0);
            
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
            
            $table->boolean('is_rejected')->default(0)->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlk_kebisingan_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_kebisingan');
    }
}
