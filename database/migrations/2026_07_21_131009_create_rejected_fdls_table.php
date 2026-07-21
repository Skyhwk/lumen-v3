<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRejectedFdlsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rejected_fdls', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel')->nullable();
            $table->string('nama_titik')->nullable();
            $table->datetime('tanggal_sampling')->nullable();
            $table->string('nama_sampler')->nullable();
            $table->string('kategori_fdl')->nullable();
            $table->text('note_reject')->nullable();
            $table->datetime('rejected_at')->nullable();
            $table->string('rejected_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rejected_fdls');
    }
}
