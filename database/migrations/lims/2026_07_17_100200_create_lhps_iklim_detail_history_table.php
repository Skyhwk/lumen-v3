<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsIklimDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_iklim_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('param', 70)->nullable();
            $table->string('hasil', 20)->nullable();
            $table->string('indeks_suhu_basah', 70)->nullable();
            $table->text('aktivitas_pekerjaan')->nullable();
            $table->string('durasi_paparan', 70)->nullable();
            $table->string('kecepatan_angin', 70)->nullable();
            $table->string('suhu_temperatur', 70)->nullable();
            $table->string('kondisi', 70)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->date('tanggal_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_iklim_detail_history');
    }
}
