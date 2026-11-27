<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bawaan peralatan aktual per TIM + TANGGAL (bukan per PT / jadwal).
 * Request per perusahaan tetap di jadwal_mobilisasi.peralatan_sampling.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('io_peralatan_tim')) {
            return;
        }

        Schema::create('io_peralatan_tim', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('tanggal');
            $table->unsignedInteger('id_cabang')->nullable();
            $table->string('petugas_key', 64);
            $table->text('petugas_sampling')->nullable();
            $table->json('batch_ids')->nullable();
            $table->json('peralatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['tanggal', 'petugas_key'], 'uq_io_peralatan_tim_tanggal_petugas');
            $table->index(['tanggal', 'is_active'], 'idx_io_peralatan_tim_tanggal_active');
            $table->index('id_cabang', 'idx_io_peralatan_tim_cabang');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('io_peralatan_tim');
    }
};
