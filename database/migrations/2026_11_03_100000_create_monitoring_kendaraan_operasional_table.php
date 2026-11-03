<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monitoring_kendaraan_operasional')) {
            Schema::create('monitoring_kendaraan_operasional', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('daftar_mobil_id');

                $table->string('nama_mobil', 150)->nullable();
                $table->string('plat_mobil', 30)->nullable();
                $table->string('code_aset', 100)->nullable();

                $table->string('jenis_dimensi_kendaraan', 20);
                $table->string('tipe_transmisi', 20);
                $table->string('kepemilikan', 20);
                $table->unsignedInteger('durasi_ketersediaan')->nullable();

                $table->string('status_kendaraan', 40);

                $table->text('keterangan')->nullable();

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('daftar_mobil_id');
                $table->index('status_kendaraan');
                $table->index(['is_active', 'status_kendaraan'], 'mko_active_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_kendaraan_operasional');
    }
};
