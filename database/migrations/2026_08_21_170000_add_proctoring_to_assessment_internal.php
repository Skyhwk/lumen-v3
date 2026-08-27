<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProctoringToAssessmentInternal extends Migration
{
    public function up()
    {
        if (Schema::hasTable('assessment_internal_attempts') && !Schema::hasColumn('assessment_internal_attempts', 'consent_at')) {
            Schema::table('assessment_internal_attempts', function (Blueprint $table) {
                $table->dateTime('consent_at')->nullable()->after('completed_at');
            });
        }

        if (Schema::hasTable('assessment_internal_sessions') && !Schema::hasColumn('assessment_internal_sessions', 'expires_at')) {
            Schema::table('assessment_internal_sessions', function (Blueprint $table) {
                $table->dateTime('expires_at')->nullable()->after('started_at');
            });
        }

        if (Schema::hasTable('assessment_internal_attempts') && !Schema::hasColumn('assessment_internal_attempts', 'activity_meta')) {
            Schema::table('assessment_internal_attempts', function (Blueprint $table) {
                $table->json('activity_meta')->nullable()->after('completion_email_sent_at');
            });
        }
    }

    public function down()
    {
        // Non-destructive: data consent, timer, dan aktivitas assessment dipertahankan.
    }
}
