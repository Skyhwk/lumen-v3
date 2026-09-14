<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUserReviewFlowToRequestKebijakan extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (!Schema::hasColumn('request_kebijakan', 'forwarded_to_user_by')) {
                $table->string('forwarded_to_user_by', 255)->nullable()->after('reviewed_at');
            }

            if (!Schema::hasColumn('request_kebijakan', 'forwarded_to_user_at')) {
                $table->timestamp('forwarded_to_user_at')->nullable()->after('forwarded_to_user_by');
            }

            if (!Schema::hasColumn('request_kebijakan', 'user_reviewed_by')) {
                $table->string('user_reviewed_by', 255)->nullable()->after('forwarded_to_user_at');
            }

            if (!Schema::hasColumn('request_kebijakan', 'user_reviewed_at')) {
                $table->timestamp('user_reviewed_at')->nullable()->after('user_reviewed_by');
            }
        });

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed',
            'pending_user_review'
        ) NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        DB::statement("UPDATE request_kebijakan SET status = 'completed' WHERE status = 'pending_user_review'");

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed'
        ) NULL");

        Schema::table('request_kebijakan', function (Blueprint $table) {
            foreach (['user_reviewed_at', 'user_reviewed_by', 'forwarded_to_user_at', 'forwarded_to_user_by'] as $column) {
                if (Schema::hasColumn('request_kebijakan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
