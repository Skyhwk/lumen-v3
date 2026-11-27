<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('monitoring_peralatan')) {
            return;
        }

        Schema::table('monitoring_peralatan', function (Blueprint $table) {
            if (!Schema::hasColumn('monitoring_peralatan', 'start_date')) {
                $column = $table->date('start_date')->nullable();

                if (Schema::hasColumn('monitoring_peralatan', 'keterangan')) {
                    $column->after('keterangan');
                } elseif (Schema::hasColumn('monitoring_peralatan', 'status_alat')) {
                    $column->after('status_alat');
                } elseif (Schema::hasColumn('monitoring_peralatan', 'id_peralatan')) {
                    $column->after('id_peralatan');
                }
            }
        });

        Schema::table('monitoring_peralatan', function (Blueprint $table) {
            if (!Schema::hasColumn('monitoring_peralatan', 'end_date')) {
                $column = $table->date('end_date')->nullable();

                if (Schema::hasColumn('monitoring_peralatan', 'start_date')) {
                    $column->after('start_date');
                } elseif (Schema::hasColumn('monitoring_peralatan', 'keterangan')) {
                    $column->after('keterangan');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('monitoring_peralatan')) {
            return;
        }

        Schema::table('monitoring_peralatan', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('monitoring_peralatan', 'end_date')) {
                $drop[] = 'end_date';
            }
            if (Schema::hasColumn('monitoring_peralatan', 'start_date')) {
                $drop[] = 'start_date';
            }

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
