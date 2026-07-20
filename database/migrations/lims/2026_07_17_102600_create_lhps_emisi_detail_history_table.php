<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsEmisiDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_emisi_detail_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('id_header');
            $table->string('no_sampel', 50);
            $table->string('nama_kendaraan', 100);
            $table->string('bobot_kendaraan', 10);
            $table->string('tahun_kendaraan', 10);
            $table->json('hasil_uji');
            $table->json('baku_mutu');
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->date('tanggal_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_emisi_detail_history');
    }
}
