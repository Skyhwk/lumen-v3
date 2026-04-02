<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class KalkulasiTargetPenjadwalan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kalkulasi_target_penjadwalan', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->string('tahun', 5)->nullable();
            
            // Menggunakan double dengan total 20 digit dan 2 angka di belakang koma
            $table->double('januari', 20, 2)->nullable();
            $table->double('februari', 20, 2)->nullable();
            $table->double('maret', 20, 2)->nullable();
            $table->double('april', 20, 2)->nullable();
            $table->double('mei', 20, 2)->nullable();
            $table->double('juni', 20, 2)->nullable();
            $table->double('juli', 20, 2)->nullable();
            $table->double('agustus', 20, 2)->nullable();
            $table->double('september', 20, 2)->nullable();
            $table->double('oktober', 20, 2)->nullable();
            $table->double('november', 20, 2)->nullable();
            $table->double('desember', 20, 2)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
