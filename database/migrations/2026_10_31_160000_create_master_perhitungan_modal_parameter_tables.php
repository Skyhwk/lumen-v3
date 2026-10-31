<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_perhitungan_modal_parameter')) {
            Schema::create('master_perhitungan_modal_parameter', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_kategori')->nullable();
                $table->string('nama_kategori', 255)->nullable();
                $table->unsignedBigInteger('id_parameter');
                $table->string('nama_parameter', 255)->nullable();
                $table->decimal('total_modal', 18, 2)->default(0);
                $table->date('tanggal_berlaku')->nullable();
                $table->unsignedBigInteger('id_hist')->nullable();
                $table->text('catatan')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();

                $table->index(['id_parameter', 'is_active', 'tanggal_berlaku'], 'idx_mpmp_param_active_tgl');
                $table->index(['id_kategori'], 'idx_mpmp_kategori');
            });
        }

        if (!Schema::hasTable('master_perhitungan_modal_parameter_komponen')) {
            Schema::create('master_perhitungan_modal_parameter_komponen', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_perhitungan_modal');
                $table->string('label', 255);
                $table->decimal('harga', 18, 2)->default(0);
                $table->unsignedSmallInteger('urutan')->default(0);
                $table->dateTime('created_at')->nullable();

                $table->index(['id_perhitungan_modal'], 'idx_mpmpk_header');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_perhitungan_modal_parameter_komponen');
        Schema::dropIfExists('master_perhitungan_modal_parameter');
    }
};
