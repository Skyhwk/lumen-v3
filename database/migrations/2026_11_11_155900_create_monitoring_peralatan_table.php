<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_peralatan')) {
            return;
        }

        Schema::create('monitoring_peralatan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_alat', 255);
            $table->string('jenis_peralatan', 255)->nullable();
            $table->enum('warna', ['Merah', 'Biru', 'Hitam'])->nullable();
            $table->string('nama_kategori_pengujian', 70)->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->string('lokasi_penempatan_alat', 70)->nullable();
            $table->text('status_alat')->nullable();
            $table->unsignedInteger('jumlah_alat_tersedia')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('kode_aset', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('id_cabang', 'mp_master_cabang_fk')
                ->references('id')
                ->on('master_cabang')
                ->restrictOnDelete();

            $table->index('nama_alat', 'mp_nama_alat_idx');
            $table->index('warna', 'mp_warna_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_peralatan');
    }
};
