<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWsFinalApprovalTables extends Migration
{
    public function up()
    {
        Schema::create('ws_final_approval_header', function (Blueprint $table) {
            $table->id();
            $table->string('no_order', 50)->nullable();
            $table->string('no_sampel', 50)->nullable();
            $table->string('periode', 50)->nullable();
            $table->json('parameter')->nullable();
            $table->string('kategori', 70)->nullable();
            $table->string('sub_kategori', 70)->nullable();
            $table->json('regulasi')->nullable();
            $table->string('nama_titik', 50)->nullable();
            $table->boolean('is_approved')->default(false);
            $table->string('approved_by', 100)->nullable();
            $table->dateTime('approved_at')->nullable();

            $table->index('no_order');
            $table->index('no_sampel');
        });

        Schema::create('ws_final_approval_detail', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ws_final_approval_header_id');
            $table->string('no_sampel', 50)->nullable();
            $table->string('parameter', 70)->nullable();
            $table->string('hasil', 50)->nullable();

            $table->foreign('ws_final_approval_header_id', 'ws_final_approval_detail_header_fk')
                ->references('id')
                ->on('ws_final_approval_header')
                ->onDelete('cascade');

            $table->index('ws_final_approval_header_id');
            $table->index('no_sampel');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ws_final_approval_detail');
        Schema::dropIfExists('ws_final_approval_header');
    }
}
