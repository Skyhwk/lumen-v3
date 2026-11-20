<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Relasi BAK (berita acara) ↔ Investigasi Awal (F1).
 * Dipakai tab Laporan BAK: sudah diproses = BAK sudah ditambahkan di Investigasi Awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bak_investigasi_berita_acara')) {
            return;
        }

        Schema::create('bak_investigasi_berita_acara', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bak_investigasi_id');
            $table->unsignedBigInteger('berita_acara_id')->comment('FK berita_acara_kejadian_disipliner.id');
            $table->timestamp('created_at')->nullable();

            $table->unique('berita_acara_id', 'bak_inv_ba_berita_uq');
            $table->index(['bak_investigasi_id', 'berita_acara_id'], 'bak_inv_ba_pair_idx');

            if (Schema::hasTable('bak_investigasi')) {
                $table->foreign('bak_investigasi_id', 'bak_inv_ba_inv_fk')
                    ->references('id')
                    ->on('bak_investigasi')
                    ->onDelete('cascade');
            }

            if (Schema::hasTable('berita_acara_kejadian_disipliner')) {
                $table->foreign('berita_acara_id', 'bak_inv_ba_berita_fk')
                    ->references('id')
                    ->on('berita_acara_kejadian_disipliner')
                    ->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bak_investigasi_berita_acara');
    }
};