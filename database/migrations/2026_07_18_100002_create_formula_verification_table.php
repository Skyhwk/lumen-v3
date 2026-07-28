<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFormulaVerificationTable extends Migration
{
    public function up()
    {
        Schema::create('formula_verification', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('formula_id')->index();
            $table->dateTime('tanggal_verifikasi');
            $table->string('no_sampel', 100);
            $table->string('hasil_sistem', 100);
            $table->string('hasil_manual', 100);
            $table->text('rumus_sistem');
            $table->string('foto_screenshot', 255)->nullable();
            $table->string('link_dokumen', 500)->nullable();
            $table->string('dokumen_filename', 255)->nullable();
            $table->enum('status_verifikasi', ['sesuai', 'tidak_sesuai']);
            $table->string('status_label', 255);
            $table->string('verifikator', 255);
            $table->text('catatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['formula_id', 'tanggal_verifikasi']);
            $table->index(['no_sampel', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('formula_verification');
    }
}
