<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogAnalisaTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_analisa')) {
            Schema::create('log_analisa', function (Blueprint $table) {
                $table->id();
                $table->string('no_sampel', 100);
                $table->string('nama_parameter', 255);
                $table->unsignedInteger('id_parameter')->nullable();
                $table->string('kategori_2', 50);
                $table->date('tanggal_jadwal')->nullable();
                $table->dateTime('ftc_laboratory')->nullable();
                $table->dateTime('ftc_verifier')->nullable();
                $table->dateTime('input_analisa')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['no_sampel', 'nama_parameter'], 'uk_log_sampel_parameter');
                $table->index('kategori_2', 'idx_log_kategori_2');
                $table->index('nama_parameter', 'idx_log_nama_parameter');
                $table->index('id_parameter', 'idx_log_id_parameter');
                $table->index('tanggal_jadwal', 'idx_log_tanggal_jadwal');
                $table->index('ftc_laboratory', 'idx_log_ftc_laboratory');
                $table->index('ftc_verifier', 'idx_log_ftc_verifier');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_analisa');
    }
}
