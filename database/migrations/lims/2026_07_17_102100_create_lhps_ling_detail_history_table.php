<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsLingDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_ling_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('akr', 20)->nullable();
            $table->string('parameter_lab', 50)->nullable();
            $table->string('parameter', 50)->nullable();
            $table->string('hasil_uji', 20)->nullable();
            $table->string('durasi', 15)->nullable();
            $table->string('attr', 20)->nullable();
            $table->text('baku_mutu')->nullable();
            $table->string('satuan', 50)->nullable();
            $table->string('methode', 100)->nullable();
            $table->string('nama_header', 100)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('no_sampel', 100)->nullable();
            $table->text('deskripsi_titik')->nullable();
            $table->string('tanggal_sampling', 100)->nullable(); // varchar di SQL asli
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_ling_detail_history');
    }
}
