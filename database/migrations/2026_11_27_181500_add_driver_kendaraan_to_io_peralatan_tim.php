<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Driver & kendaraan aktual level TIM (Informasi Operasional),
 * terpisah dari jadwal.driver / jadwal.kendaraan (asal Sampling Plan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('io_peralatan_tim')) {
            return;
        }

        Schema::table('io_peralatan_tim', function (Blueprint $table) {
            if (!Schema::hasColumn('io_peralatan_tim', 'driver')) {
                $table->string('driver', 150)->nullable()->after('peralatan');
            }
            if (!Schema::hasColumn('io_peralatan_tim', 'kendaraan')) {
                $table->string('kendaraan', 150)->nullable()->after('driver');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('io_peralatan_tim')) {
            return;
        }

        Schema::table('io_peralatan_tim', function (Blueprint $table) {
            if (Schema::hasColumn('io_peralatan_tim', 'kendaraan')) {
                $table->dropColumn('kendaraan');
            }
            if (Schema::hasColumn('io_peralatan_tim', 'driver')) {
                $table->dropColumn('driver');
            }
        });
    }
};
