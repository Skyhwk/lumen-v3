<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recruitment_general_question_categories')) {
            Schema::create('recruitment_general_question_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('division_id');
                $table->string('grade', 100);
                $table->unsignedInteger('question_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['division_id', 'grade'], 'recruitment_general_category_scope_unique');
                $table->index(['division_id', 'grade', 'is_active'], 'recruitment_general_category_scope_index');
            });
        } else {
            Schema::table('recruitment_general_question_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('recruitment_general_question_categories', 'division_id')) $table->unsignedBigInteger('division_id')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_categories', 'grade')) $table->string('grade', 100)->nullable();
                if (!Schema::hasColumn('recruitment_general_question_categories', 'question_count')) $table->unsignedInteger('question_count')->default(0);
                if (!Schema::hasColumn('recruitment_general_question_categories', 'is_active')) $table->boolean('is_active')->default(true);
                if (!Schema::hasColumn('recruitment_general_question_categories', 'created_by')) $table->string('created_by')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_categories', 'updated_by')) $table->string('updated_by')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_categories', 'created_at')) $table->timestamp('created_at')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_categories', 'updated_at')) $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('recruitment_general_questions')) {
            Schema::create('recruitment_general_questions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('category_id');
                $table->text('question_text');
                $table->string('status', 20)->default('active');
                $table->boolean('is_active')->default(true);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->index(['category_id', 'is_active', 'status'], 'recruitment_general_questions_category_index');
            });
        } else {
            Schema::table('recruitment_general_questions', function (Blueprint $table) {
                if (!Schema::hasColumn('recruitment_general_questions', 'category_id')) $table->unsignedBigInteger('category_id')->nullable();
                if (!Schema::hasColumn('recruitment_general_questions', 'question_text')) $table->text('question_text')->nullable();
                if (!Schema::hasColumn('recruitment_general_questions', 'status')) $table->string('status', 20)->default('active');
                if (!Schema::hasColumn('recruitment_general_questions', 'is_active')) $table->boolean('is_active')->default(true);
                if (!Schema::hasColumn('recruitment_general_questions', 'created_by')) $table->string('created_by')->nullable();
                if (!Schema::hasColumn('recruitment_general_questions', 'updated_by')) $table->string('updated_by')->nullable();
                if (!Schema::hasColumn('recruitment_general_questions', 'created_at')) $table->timestamp('created_at')->nullable();
                if (!Schema::hasColumn('recruitment_general_questions', 'updated_at')) $table->timestamp('updated_at')->nullable();
            });
        }

        if (!Schema::hasTable('recruitment_general_question_options')) {
            Schema::create('recruitment_general_question_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('question_id');
                $table->text('option_text');
                $table->unsignedBigInteger('position_id');
                $table->unsignedInteger('option_order')->default(1);
                $table->timestamps();
                $table->index(['question_id', 'option_order'], 'recruitment_general_options_question_index');
                $table->index('position_id', 'recruitment_general_options_position_index');
            });
        } else {
            Schema::table('recruitment_general_question_options', function (Blueprint $table) {
                if (!Schema::hasColumn('recruitment_general_question_options', 'question_id')) $table->unsignedBigInteger('question_id')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_options', 'option_text')) $table->text('option_text')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_options', 'position_id')) $table->unsignedBigInteger('position_id')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_options', 'option_order')) $table->unsignedInteger('option_order')->default(1);
                if (!Schema::hasColumn('recruitment_general_question_options', 'created_at')) $table->timestamp('created_at')->nullable();
                if (!Schema::hasColumn('recruitment_general_question_options', 'updated_at')) $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recruitment_general_question_options')) Schema::drop('recruitment_general_question_options');
        if (Schema::hasTable('recruitment_general_questions')) Schema::drop('recruitment_general_questions');
        if (Schema::hasTable('recruitment_general_question_categories')) Schema::drop('recruitment_general_question_categories');
    }
};
