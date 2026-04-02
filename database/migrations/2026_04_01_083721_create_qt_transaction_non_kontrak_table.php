<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQtTransactionNonKontrakTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qt_transaction_non_kontrak', function (Blueprint $table) {
            $table->string('uuid')->unique();
            $table->string('id_pelanggan')->length(10);
            $table->string('nama_pelanggan');
            $table->string('no_qt');
            $table->json('rekap_transactions');
            $table->string('sales_id');
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
        Schema::dropIfExists('qt_transaction_non_kontrak');
    }
}
