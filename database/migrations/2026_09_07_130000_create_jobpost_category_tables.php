<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jobpost_categories')) {
            Schema::create('jobpost_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->json('assigned_user_ids')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('jobpost_category_mappings')) {
            Schema::create('jobpost_category_mappings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('jobpost_category_id');
                $table->unsignedBigInteger('division_id');
                $table->string('grade', 100);
                $table->unsignedBigInteger('position_id');
                $table->timestamps();
                $table->unique(['jobpost_category_id', 'division_id', 'grade', 'position_id'], 'jobpost_category_mapping_unique');
                $table->index(['division_id', 'position_id', 'grade'], 'jobpost_category_lookup');
            });
        }
        if (Schema::hasTable('personnel_requests') && !Schema::hasColumn('personnel_requests', 'jobpost_category_id')) {
            Schema::table('personnel_requests', function (Blueprint $table) {
                $table->unsignedBigInteger('jobpost_category_id')->nullable()->after('divisi_alias');
                $table->index('jobpost_category_id');
            });
        }
        if (Schema::hasTable('recruitment_general_question_categories') && !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id')) {
            Schema::table('recruitment_general_question_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('jobpost_category_id')->nullable()->after('id');
                $table->index(['jobpost_category_id', 'grade'], 'recruitment_general_jobpost_scope_index');
            });
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: configured categories can already be used by published recruitment data.
    }
};
