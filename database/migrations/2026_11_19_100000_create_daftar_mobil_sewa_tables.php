<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daftar_mobil_sewa')) {
            Schema::create('daftar_mobil_sewa', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('plat_mobil', 30);
                $table->string('merk_mobil', 100);
                $table->string('tipe_mobil', 100);
                $table->string('nomor_rangka', 100)->nullable();
                $table->string('nomor_mesin', 100)->nullable();
                $table->string('warna_mobil', 50);
                $table->unsignedSmallInteger('tahun_perakitan')->nullable();

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('plat_mobil', 'daftar_mobil_sewa_plat_idx');
                $table->index('is_active', 'daftar_mobil_sewa_active_idx');
                $table->index(['is_active', 'plat_mobil'], 'daftar_mobil_sewa_active_plat_idx');
            });
        }

        if (!Schema::hasTable('tanggal_daftar_mobil_sewa')) {
            Schema::create('tanggal_daftar_mobil_sewa', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('daftar_mobil_sewa_id');
                $table->date('tanggal_sewa');

                $table->string('created_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true);

                $table->index('daftar_mobil_sewa_id', 'tgl_mobil_sewa_header_idx');
                $table->index('tanggal_sewa', 'tgl_mobil_sewa_date_idx');
                $table->index(
                    ['daftar_mobil_sewa_id', 'tanggal_sewa', 'is_active'],
                    'tgl_mobil_sewa_header_date_active_idx'
                );
                $table->index(
                    ['is_active', 'tanggal_sewa'],
                    'tgl_mobil_sewa_active_date_idx'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tanggal_daftar_mobil_sewa');
        Schema::dropIfExists('daftar_mobil_sewa');
    }
};
