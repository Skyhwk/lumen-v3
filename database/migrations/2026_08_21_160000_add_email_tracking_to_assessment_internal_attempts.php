<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEmailTrackingToAssessmentInternalAttempts extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal_attempts')) {
            return;
        }

        Schema::table('assessment_internal_attempts', function (Blueprint $table) {
            if (!Schema::hasColumn('assessment_internal_attempts', 'registration_email_sent_at')) {
                $table->dateTime('registration_email_sent_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('assessment_internal_attempts', 'completion_email_sent_at')) {
                $table->dateTime('completion_email_sent_at')->nullable()->after('registration_email_sent_at');
            }
        });
    }

    public function down()
    {
        // Non-destructive: histori pengiriman email tidak dihapus otomatis.
    }
}
