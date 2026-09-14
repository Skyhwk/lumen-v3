<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUserRejectReviewFlow extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        Schema::table('request_kebijakan', function (Blueprint $table) {
            if (!Schema::hasColumn('request_kebijakan', 'user_review_rejected_by')) {
                $table->string('user_review_rejected_by', 255)->nullable()->after('user_reviewed_at');
            }

            if (!Schema::hasColumn('request_kebijakan', 'user_review_rejected_at')) {
                $table->timestamp('user_review_rejected_at')->nullable()->after('user_review_rejected_by');
            }

            if (!Schema::hasColumn('request_kebijakan', 'user_review_rejected_note')) {
                $table->text('user_review_rejected_note')->nullable()->after('user_review_rejected_at');
            }
        });

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed',
            'pending_user_review',
            'pending_user_reject_review'
        ) NULL");

        if (Schema::hasTable('drafting_kebijakan')) {
            Schema::table('drafting_kebijakan', function (Blueprint $table) {
                if (!Schema::hasColumn('drafting_kebijakan', 'review_rejected_source')) {
                    $table->string('review_rejected_source', 50)->nullable()->after('review_rejected_note');
                }

                if (!Schema::hasColumn('drafting_kebijakan', 'review_reject_used_user_note')) {
                    $table->boolean('review_reject_used_user_note')->default(false)->after('review_rejected_source');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        DB::statement("UPDATE request_kebijakan SET status = 'pending_user_review' WHERE status = 'pending_user_reject_review'");

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed',
            'pending_user_review'
        ) NULL");

        Schema::table('request_kebijakan', function (Blueprint $table) {
            foreach (['user_review_rejected_note', 'user_review_rejected_at', 'user_review_rejected_by'] as $column) {
                if (Schema::hasColumn('request_kebijakan', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('drafting_kebijakan')) {
            Schema::table('drafting_kebijakan', function (Blueprint $table) {
                foreach (['review_reject_used_user_note', 'review_rejected_source'] as $column) {
                    if (Schema::hasColumn('drafting_kebijakan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
