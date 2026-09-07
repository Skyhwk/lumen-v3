<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProcessedStatusToRequestKebijakanTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (!Schema::hasColumn('request_kebijakan', 'processed_by')) {
                $table->string('processed_by', 255)->nullable()->after('approval_at');
            }

            if (!Schema::hasColumn('request_kebijakan', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed_by');
            }
        });

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed'
        ) NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        DB::statement("UPDATE request_kebijakan SET status = 'approved' WHERE status = 'processed'");

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed'
        ) NULL");

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (Schema::hasColumn('request_kebijakan', 'processed_at')) {
                $table->dropColumn('processed_at');
            }

            if (Schema::hasColumn('request_kebijakan', 'processed_by')) {
                $table->dropColumn('processed_by');
            }
        });
    }
}
