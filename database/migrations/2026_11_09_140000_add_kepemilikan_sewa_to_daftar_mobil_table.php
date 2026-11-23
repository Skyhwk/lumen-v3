<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('daftar_mobil')) {
            return;
        }

        Schema::table('daftar_mobil', function (Blueprint $table) {
            if (!Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
                $column = $table->string('kepemilikan', 20)->default('Internal');
                if (Schema::hasColumn('daftar_mobil', 'tahun_perakitan')) {
                    $column->after('tahun_perakitan');
                }
            }
        });

        Schema::table('daftar_mobil', function (Blueprint $table) {
            if (!Schema::hasColumn('daftar_mobil', 'tanggal_mulai_sewa')) {
                $column = $table->date('tanggal_mulai_sewa')->nullable();
                if (Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
                    $column->after('kepemilikan');
                }
            }
        });

        Schema::table('daftar_mobil', function (Blueprint $table) {
            if (!Schema::hasColumn('daftar_mobil', 'tanggal_akhir_sewa')) {
                $column = $table->date('tanggal_akhir_sewa')->nullable();
                if (Schema::hasColumn('daftar_mobil', 'tanggal_mulai_sewa')) {
                    $column->after('tanggal_mulai_sewa');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('daftar_mobil')) {
            return;
        }

        Schema::table('daftar_mobil', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('daftar_mobil', 'tanggal_akhir_sewa')) {
                $drop[] = 'tanggal_akhir_sewa';
            }
            if (Schema::hasColumn('daftar_mobil', 'tanggal_mulai_sewa')) {
                $drop[] = 'tanggal_mulai_sewa';
            }
            if (Schema::hasColumn('daftar_mobil', 'kepemilikan')) {
                $drop[] = 'kepemilikan';
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
