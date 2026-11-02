<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('konfigurasi_label_parameter')) {
            return;
        }

        Schema::create('konfigurasi_label_parameter', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_parameter')->unique();
            $table->string('nama_parameter', 255)->nullable();
            $table->string('volume', 50)->nullable();
            $table->string('regen', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->string('deleted_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['is_active', 'id_parameter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konfigurasi_label_parameter');
    }
};
