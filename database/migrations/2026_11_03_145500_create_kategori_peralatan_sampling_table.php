<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        Schema::create('kategori_peralatan_sampling', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_alat', 255);
            $table->string('jenis_peralatan', 255)->nullable();
            $table->enum('warna', ['Merah', 'Biru', 'Hitam'])->nullable();
            $table->string('nama_kategori_pengujian', 70)->nullable();
            $table->unsignedBigInteger('id_cabang')->nullable();
            $table->string('lokasi_penempatan_alat', 70)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->foreign('id_cabang', 'kps_master_cabang_fk')
                ->references('id')
                ->on('master_cabang')
                ->restrictOnDelete();

            $table->index('nama_alat', 'kps_nama_alat_idx');
            $table->index('warna', 'kps_warna_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_peralatan_sampling');
    }
};
