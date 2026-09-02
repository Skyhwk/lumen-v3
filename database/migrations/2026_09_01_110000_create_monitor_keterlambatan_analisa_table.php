<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMonitorKeterlambatanAnalisaTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monitor_keterlambatan_analisa')) {
            Schema::create('monitor_keterlambatan_analisa', function (Blueprint $table) {
                $table->id();
                $table->string('no_sampel', 100);
                $table->string('nama_parameter', 255);
                $table->string('kategori_2', 50);
                $table->dateTime('ftc_laboratory')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['no_sampel', 'nama_parameter'], 'uk_sampel_parameter');
                $table->index('kategori_2', 'idx_kategori_2');
                $table->index('nama_parameter', 'idx_nama_parameter');
                $table->index('ftc_laboratory', 'idx_ftc_laboratory');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitor_keterlambatan_analisa');
    }
}
