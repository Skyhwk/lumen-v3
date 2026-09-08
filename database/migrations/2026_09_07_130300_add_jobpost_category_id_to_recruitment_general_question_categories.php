<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recruitment_general_question_categories') && !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id')) {
            Schema::table('recruitment_general_question_categories', function (Blueprint $table) {
                $table->unsignedBigInteger('jobpost_category_id')->nullable()->after('id');
                $table->index(['jobpost_category_id', 'grade'], 'recruitment_general_jobpost_scope_index');
            });
        }
    }

    public function down(): void
    {
        // Non-destructive: existing assessment category configuration can use this relation.
    }
};
