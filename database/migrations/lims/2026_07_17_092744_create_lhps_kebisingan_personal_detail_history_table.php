<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsKebisinganPersonalDetailHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lhps_kebisingan_personal_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            
            $table->string('no_sampel', 50)->nullable();
            $table->string('param', 70)->nullable();
            $table->string('nama_pekerja', 70)->nullable();
            $table->text('lokasi_keterangan')->nullable();
            
            $table->string('leq_lm', 20)->nullable();
            $table->string('leq_ls', 20)->nullable();
            $table->string('min', 20)->nullable();
            $table->string('paparan', 70)->nullable();
            $table->string('max', 20)->nullable();
            $table->string('hasil_uji', 20)->nullable();
            $table->string('nab', 70)->nullable();
            $table->string('leq_lsm', 100)->nullable();
            
            $table->string('titik_koordinat', 50)->nullable();
            $table->string('tanggal_sampling_backup', 100)->nullable();
            $table->date('tanggal_sampling')->nullable();
            
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_kebisingan_personal_detail_history');
    }
}
