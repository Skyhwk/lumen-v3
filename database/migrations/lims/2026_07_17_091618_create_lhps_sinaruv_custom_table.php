<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsSinaruvCustomTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('lhps_sinaruv_custom', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->integer('page')->nullable();
            
            $table->string('akr', 20)->nullable();
            $table->string('attr', 20)->nullable();
            $table->string('nab', 100)->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->string('parameter', 70)->nullable();
            $table->text('keterangan')->nullable();
            
            // Detail Pekerjaan & Paparan
            $table->text('aktivitas_pekerjaan')->nullable();
            $table->string('sumber_radiasi', 100)->nullable();
            $table->string('waktu_pemaparan', 100)->nullable();
            
            // Titik Paparan
            $table->string('mata', 100)->nullable();
            $table->string('siku', 100)->nullable();
            $table->string('betis', 100)->nullable();
            
            // Metode & Waktu
            $table->string('methode', 100)->nullable();
            $table->date('tanggal_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_sinaruv_custom');
    }
}
