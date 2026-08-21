<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssessmentInternalAttemptTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal_attempts')) {
            Schema::create('assessment_internal_attempts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('assessment_internal_id');
                $table->string('email');
                $table->string('participant_name')->nullable();
                $table->string('access_token_hash', 64)->nullable();
                $table->string('status', 30)->default('in_progress');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('last_activity_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('profile_completed_at')->nullable();
                $table->dateTime('consent_at')->nullable();
                $table->dateTime('registration_email_sent_at')->nullable();
                $table->dateTime('completion_email_sent_at')->nullable();
                $table->json('activity_meta')->nullable();
                $table->timestamps();

                $table->unique(['assessment_internal_id', 'email'], 'assessment_internal_attempt_email_unique');
                $table->index(['email', 'status'], 'assessment_internal_attempt_email_status_idx');
            });
        }

        if (!Schema::hasTable('assessment_internal_sessions')) {
            Schema::create('assessment_internal_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('assessment_internal_attempt_id');
                $table->unsignedBigInteger('question_category_id')->nullable();
                $table->unsignedInteger('session_order');
                $table->string('category_name')->nullable();
                $table->unsignedInteger('duration_minutes')->nullable();
                $table->longText('questions_json');
                $table->longText('answers_json')->nullable();
                $table->longText('result_json')->nullable();
                $table->string('status', 30)->default('pending');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['assessment_internal_attempt_id', 'session_order'], 'assessment_internal_attempt_session_unique');
            });
        }

    }

    public function down()
    {
        // Non-destructive: attempt dan jawaban assessment tidak dihapus otomatis.
    }
}
