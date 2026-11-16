<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dfus')) {
            return;
        }

        if (!Schema::hasColumn('dfus', 'keterangan_sales')) {
            Schema::table('dfus', function (Blueprint $table) {
                $column = $table->text('keterangan_sales')->nullable();
                if (Schema::hasColumn('dfus', 'keterangan_activity')) {
                    $column->after('keterangan_activity');
                } elseif (Schema::hasColumn('dfus', 'keterangan')) {
                    $column->after('keterangan');
                }
            });
        }

        if (!Schema::hasColumn('dfus', 'keterangan_by')) {
            Schema::table('dfus', function (Blueprint $table) {
                $column = $table->string('keterangan_by', 150)->nullable();
                if (Schema::hasColumn('dfus', 'keterangan_sales')) {
                    $column->after('keterangan_sales');
                }
            });
        }

        if (!Schema::hasColumn('dfus', 'keterangan_at')) {
            Schema::table('dfus', function (Blueprint $table) {
                $column = $table->timestamp('keterangan_at')->nullable();
                if (Schema::hasColumn('dfus', 'keterangan_by')) {
                    $column->after('keterangan_by');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('dfus')) {
            return;
        }

        Schema::table('dfus', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'keterangan_sales',
                'keterangan_by',
                'keterangan_at',
            ], function ($column) {
                return Schema::hasColumn('dfus', $column);
            }));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
