<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMobilisasiOperasionalTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mobilisasi_operasional')) {
            Schema::create('mobilisasi_operasional', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_mobil')->nullable()->index();
                $table->string('plat_mobil', 50)->nullable()->index();
                $table->unsignedBigInteger('id_driver')->nullable()->index();
                $table->string('nama_driver', 150)->nullable()->index();
                $table->date('tanggal_sampling')->index();
                $table->date('tanggal_pulang')->nullable();
                $table->time('jam_keberangkatan')->nullable();
                $table->unsignedTinyInteger('durasi')->default(0);
                $table->text('catatan')->nullable();
                $table->string('created_by', 150)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true)->index();

                $table->index(['tanggal_sampling', 'is_active']);
            });
        }

        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            Schema::create('mobilisasi_operasional_detail', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_mo')->index();
                $table->unsignedBigInteger('id_jadwal')->index();
                $table->string('sampler', 255)->nullable();
                $table->string('no_quotation', 100)->nullable();
                $table->string('nama_perusahaan', 255)->nullable();
                $table->string('jam_mulai', 20)->nullable();
                $table->string('jam_selesai', 20)->nullable();
                $table->string('wilayah', 150)->nullable();
                $table->unsignedTinyInteger('durasi')->nullable();
                $table->string('created_by', 150)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->string('deleted_by', 150)->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->boolean('is_active')->default(true)->index();

                $table->index(['id_mo', 'is_active']);
                $table->index(['id_jadwal', 'is_active']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('mobilisasi_operasional_detail');
        Schema::dropIfExists('mobilisasi_operasional');
    }
}
