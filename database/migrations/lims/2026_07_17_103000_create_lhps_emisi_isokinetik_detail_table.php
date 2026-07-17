<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsEmisiIsokinetikDetailTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_emisi_isokinetik_detail', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('akr', 20)->nullable();
            $table->string('parameter_lab', 50)->nullable();
            $table->string('parameter', 50)->nullable();
            $table->string('hasil_uji', 20)->nullable();
            $table->string('hasil_terkoreksi', 20)->nullable();
            $table->string('baku_mutu', 50)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->string('spesifikasi_metode', 150)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_emisi_isokinetik_detail');
    }
}
