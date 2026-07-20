<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganKebisinganPersonalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_kebisingan_personal', function (Blueprint $table) {
            $table->id();
            
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            $table->string('titik_koordinat', 30)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('departemen', 50)->nullable();
            $table->text('sumber_kebisingan')->nullable();
            $table->string('jarak_sumber_kebisingan', 50)->nullable();
            $table->string('waktu_paparan', 25)->nullable();
            $table->string('value_kebisingan', 20)->nullable();
            $table->string('filename', 255)->nullable();
            $table->string('jam_mulai_pengujian', 10)->nullable();
            $table->string('total_waktu_istirahat_personal', 10)->nullable();
            $table->string('jam_akhir_pengujian', 10)->nullable();
            $table->string('waktu_pengukuran', 20)->nullable();
            $table->string('aktifitas', 100)->nullable();
            
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            
            $table->integer('permission')->default(0);
            $table->integer('is_blocked')->default(0);
            
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->boolean('is_rejected')->default(0)->nullable();
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dlkp_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_kebisingan_personal');
    }
}
