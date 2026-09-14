<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewFieldsToKebijakanTables extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('request_kebijakan')) {
            Schema::table('request_kebijakan', function (Blueprint $table) {
                if (!Schema::hasColumn('request_kebijakan', 'reviewed_by')) {
                    $table->string('reviewed_by', 255)->nullable()->after('processed_at');
                }

                if (!Schema::hasColumn('request_kebijakan', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
            });
        }

        if (Schema::hasTable('drafting_kebijakan')) {
            Schema::table('drafting_kebijakan', function (Blueprint $table) {
                if (!Schema::hasColumn('drafting_kebijakan', 'review_rejected_by')) {
                    $table->string('review_rejected_by', 255)->nullable()->after('submitted_at');
                }

                if (!Schema::hasColumn('drafting_kebijakan', 'review_rejected_at')) {
                    $table->timestamp('review_rejected_at')->nullable()->after('review_rejected_by');
                }

                if (!Schema::hasColumn('drafting_kebijakan', 'review_rejected_note')) {
                    $table->text('review_rejected_note')->nullable()->after('review_rejected_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('request_kebijakan')) {
            Schema::table('request_kebijakan', function (Blueprint $table) {
                if (Schema::hasColumn('request_kebijakan', 'reviewed_at')) {
                    $table->dropColumn('reviewed_at');
                }

                if (Schema::hasColumn('request_kebijakan', 'reviewed_by')) {
                    $table->dropColumn('reviewed_by');
                }
            });
        }

        if (Schema::hasTable('drafting_kebijakan')) {
            Schema::table('drafting_kebijakan', function (Blueprint $table) {
                if (Schema::hasColumn('drafting_kebijakan', 'review_rejected_note')) {
                    $table->dropColumn('review_rejected_note');
                }

                if (Schema::hasColumn('drafting_kebijakan', 'review_rejected_at')) {
                    $table->dropColumn('review_rejected_at');
                }

                if (Schema::hasColumn('drafting_kebijakan', 'review_rejected_by')) {
                    $table->dropColumn('review_rejected_by');
                }
            });
        }
    }
}
