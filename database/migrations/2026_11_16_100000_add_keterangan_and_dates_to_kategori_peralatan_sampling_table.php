<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
                $column = $table->text('keterangan')->nullable();

                if (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                    $column->after('status_alat');
                } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'kode_alat')) {
                    $column->after('kode_alat');
                } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'lokasi_penempatan_alat')) {
                    $column->after('lokasi_penempatan_alat');
                }
            }
        });

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_peralatan_sampling', 'start_date')) {
                $column = $table->date('start_date')->nullable();

                if (Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
                    $column->after('keterangan');
                } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'status_alat')) {
                    $column->after('status_alat');
                }
            }
        });

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            if (!Schema::hasColumn('kategori_peralatan_sampling', 'end_date')) {
                $column = $table->date('end_date')->nullable();

                if (Schema::hasColumn('kategori_peralatan_sampling', 'start_date')) {
                    $column->after('start_date');
                } elseif (Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
                    $column->after('keterangan');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kategori_peralatan_sampling')) {
            return;
        }

        Schema::table('kategori_peralatan_sampling', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('kategori_peralatan_sampling', 'end_date')) {
                $drop[] = 'end_date';
            }
            if (Schema::hasColumn('kategori_peralatan_sampling', 'start_date')) {
                $drop[] = 'start_date';
            }
            if (Schema::hasColumn('kategori_peralatan_sampling', 'keterangan')) {
                $drop[] = 'keterangan';
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
