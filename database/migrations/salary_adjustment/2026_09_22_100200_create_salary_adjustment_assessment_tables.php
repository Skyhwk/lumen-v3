<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_assessments')) {
            Schema::create('salary_adjustment_assessments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('request_id')->unique();
                $table->string('token', 128)->unique();
                $table->unsignedBigInteger('question_category_id');
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->boolean('has_time_limit')->default(true);
                $table->string('link_url', 500)->nullable();
                $table->boolean('is_link_active')->default(true);
                $table->string('link_generated_by', 255)->nullable();
                $table->timestamp('link_generated_at')->nullable();
                $table->timestamp('link_deactivated_at')->nullable();
                $table->string('attempt_status', 30)->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->decimal('total_score', 8, 2)->nullable();
                $table->json('result_json')->nullable();
                $table->timestamps();

                $table->index('token');
                $table->index('attempt_status');
            });
        }

        if (!Schema::hasTable('salary_adjustment_assessment_sessions')) {
            Schema::create('salary_adjustment_assessment_sessions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('assessment_id');
                $table->unsignedTinyInteger('session_order')->default(1);
                $table->unsignedBigInteger('question_category_id')->nullable();
                $table->string('category_name', 255)->nullable();
                $table->unsignedInteger('question_count')->default(0);
                $table->unsignedInteger('duration_minutes')->default(0);
                $table->json('questions_json')->nullable();
                $table->json('answers_json')->nullable();
                $table->json('result_json')->nullable();
                $table->string('status', 30)->default('pending');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('assessment_id');
                $table->unique(['assessment_id', 'session_order'], 'sag_assessment_session_order_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_adjustment_assessment_sessions');
        Schema::dropIfExists('salary_adjustment_assessments');
    }
};
