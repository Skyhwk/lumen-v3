<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoriPrintingTable extends Migration
{
    public function up()
    {
        Schema::create('histori_printing', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('karyawan');
            $table->timestamp('timestamp')->useCurrent();
            $table->enum('status', ['done', 'failed']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('histori_printing');
    }
}
