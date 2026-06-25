<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('daily_qsd', 'tanggal_order')) {
            Schema::table('daily_qsd', function (Blueprint $table) {
                $table->date('tanggal_order')->nullable()->after('tanggal_sampling_min');
            });
        }

        DB::statement("
            UPDATE daily_qsd dq
            INNER JOIN order_header oh
                ON oh.no_order = dq.no_order
                AND oh.is_active = 1
            SET dq.tanggal_order = DATE(oh.tanggal_order)
            WHERE dq.tanggal_order IS NULL
                AND oh.tanggal_order IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('daily_qsd', 'tanggal_order')) {
            Schema::table('daily_qsd', function (Blueprint $table) {
                $table->dropColumn('tanggal_order');
            });
        }
    }
};
