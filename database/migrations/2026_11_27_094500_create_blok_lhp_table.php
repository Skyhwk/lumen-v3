<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('blok_lhp')) {
            return;
        }

        Schema::create('blok_lhp', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cfr', 100);
            $table->string('no_order', 100)->nullable();
            $table->string('periode', 50)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->text('keterangan_titik')->nullable();
            $table->text('alasan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->string('deleted_by', 100)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['cfr', 'is_active'], 'idx_blok_lhp_cfr_active');
            $table->index(['no_order', 'is_active'], 'idx_blok_lhp_order_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blok_lhp');
    }
};
