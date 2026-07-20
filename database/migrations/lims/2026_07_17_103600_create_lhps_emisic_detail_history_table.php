<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLhpsEmisicDetailHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('lhps_emisic_detail_history', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_header')->nullable();
            $table->string('akr', 20)->nullable();
            $table->string('parameter_lab', 50)->nullable();
            $table->string('parameter', 50)->nullable();
            $table->string('C', 20)->nullable();
            $table->string('C1', 20)->nullable();
            $table->string('C2', 20)->nullable();
            $table->string('terukur', 50)->nullable();
            $table->string('terkoreksi', 50)->nullable();
            $table->string('attr', 20)->nullable();
            $table->string('baku_mutu', 50)->nullable();
            $table->string('satuan', 30)->nullable();
            $table->string('spesifikasi_metode', 150)->nullable();
            $table->string('created_by', 150)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhps_emisic_detail_history');
    }
}
