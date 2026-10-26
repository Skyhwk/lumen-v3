<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbsensiMobileTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('absensi_mobile')) {
            return;
        }

        Schema::create('absensi_mobile', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id')->index();
            $table->date('tanggal')->index();
            $table->time('jam')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance', 10, 2)->nullable();
            $table->string('selfie', 255)->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('absensi_mobile');
    }
}
