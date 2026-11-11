<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qr_psikologi')) {
            return;
        }

        Schema::table('qr_psikologi', function (Blueprint $table) {
            if (!Schema::hasColumn('qr_psikologi', 'download_count')) {
                $column = $table->unsignedInteger('download_count')->default(0);
                if (Schema::hasColumn('qr_psikologi', 'expired')) {
                    $column->after('expired');
                }
            }

            if (!Schema::hasColumn('qr_psikologi', 'print_count')) {
                $column = $table->unsignedInteger('print_count')->default(0);
                if (Schema::hasColumn('qr_psikologi', 'download_count')) {
                    $column->after('download_count');
                } elseif (Schema::hasColumn('qr_psikologi', 'expired')) {
                    $column->after('expired');
                }
            }

            if (!Schema::hasColumn('qr_psikologi', 'email_count')) {
                $column = $table->unsignedInteger('email_count')->default(0);
                if (Schema::hasColumn('qr_psikologi', 'print_count')) {
                    $column->after('print_count');
                } elseif (Schema::hasColumn('qr_psikologi', 'download_count')) {
                    $column->after('download_count');
                } elseif (Schema::hasColumn('qr_psikologi', 'expired')) {
                    $column->after('expired');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('qr_psikologi')) {
            return;
        }

        Schema::table('qr_psikologi', function (Blueprint $table) {
            foreach (['email_count', 'print_count', 'download_count'] as $column) {
                if (Schema::hasColumn('qr_psikologi', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
