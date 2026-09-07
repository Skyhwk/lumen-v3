<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recruitment_general_question_categories') || !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id')) return;
        $indexNames = collect(DB::select('SHOW INDEX FROM recruitment_general_question_categories'))->pluck('Key_name')->unique();
        Schema::table('recruitment_general_question_categories', function (Blueprint $table) use ($indexNames) {
            if ($indexNames->contains('recruitment_general_category_scope_unique')) $table->dropUnique('recruitment_general_category_scope_unique');
            if (!$indexNames->contains('recruitment_general_jobpost_category_grade_unique')) $table->unique(['jobpost_category_id', 'grade'], 'recruitment_general_jobpost_category_grade_unique');
        });
    }

    public function down(): void
    {
        // Non-destructive: changing this key back can invalidate configured category scopes.
    }
};
