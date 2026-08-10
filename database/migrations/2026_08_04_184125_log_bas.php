<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LogBas extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_bas', function (Blueprint $table) {
            $table->id(); 
            $table->string('periode')->nullable();
            $table->string('no_quotation')->nullable();
            $table->string('no_order')->nullable();
            $table->string('sales_penanggung_jawab')->nullable();
            $table->date('tanggal_tugas')->nullable();
            $table->string('durasi')->nullable();
            $table->text('sampler')->nullable();
            $table->json('kategori')->nullable();
            $table->string('admin_jadwal')->nullable();
            $table->dateTime('tanggal_dijadwalkan')->nullable();
            $table->string('admin_persiapan')->nullable();
            $table->dateTime('tanggal_persiapan')->nullable();
            $table->string('no_persiapan')->nullable();
            $table->string('filename_persiapan')->nullable();
            $table->string('no_stps')->nullable();
            $table->string('filename_stps')->nullable();
            $table->string('no_cs')->nullable();
            $table->string('filename_cs')->nullable();
            $table->string('no_bas')->nullable();
            $table->string('filename_bas')->nullable();
            $table->json('data_bas')->nullable();
            $table->json('no_sampel')->nullable();
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('log_bas');
    }
}
