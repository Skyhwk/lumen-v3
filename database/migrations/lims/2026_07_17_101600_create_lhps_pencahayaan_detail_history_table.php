<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsPencahayaanDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_pencahayaan_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->string('param', 70)->nullable();
            $table->text('lokasi_keterangan')->nullable();
            $table->string('hasil_uji', 20)->nullable();
            $table->string('sumber_cahaya', 70)->nullable();
            $table->string('jenis_pengukuran', 70)->nullable();
            $table->string('nab', 70)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->date('tanggal_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_pencahayaan_detail_history');
    }
}
