<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsGetaranCustomTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_getaran_custom', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->integer('page')->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->string('aktivitas', 100)->nullable();
            $table->string('sumber_get', 70)->nullable();
            $table->string('w_paparan', 20)->nullable();
            $table->string('param', 70)->nullable();
            $table->string('x', 20)->nullable();
            $table->string('y', 20)->nullable();
            $table->string('z', 20)->nullable();
            $table->string('nab', 70)->nullable();
            $table->string('hasil', 20)->nullable();
            $table->string('tipe_getaran', 30)->nullable();
            $table->string('percepatan', 20)->nullable();
            $table->string('kecepatan', 20)->nullable();
            $table->date('tanggal_sampling')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_getaran_custom');
    }
}
