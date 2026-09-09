<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jobpost_categories') || !Schema::hasTable('jobpost_category_mappings') || !Schema::hasTable('recruitment_general_question_categories') || !Schema::hasColumn('recruitment_general_question_categories', 'jobpost_category_id')) return;
        $now = Carbon::now();
        DB::table('recruitment_general_question_categories')->whereNotNull('jobpost_category_id')->update(['is_active' => 0, 'updated_at' => $now]);
        $scopes = DB::table('jobpost_category_mappings as map')->join('jobpost_categories as category', 'category.id', '=', 'map.jobpost_category_id')
            ->where('category.is_active', 1)->groupBy('map.jobpost_category_id', 'map.grade')->get(['map.jobpost_category_id', 'map.grade']);
        foreach ($scopes as $scope) {
            $existing = DB::table('recruitment_general_question_categories')->where('jobpost_category_id', $scope->jobpost_category_id)->where('grade', $scope->grade)->first();
            if ($existing) {
                DB::table('recruitment_general_question_categories')->where('id', $existing->id)->update(['is_active' => 1, 'updated_at' => $now]);
            } else {
                DB::table('recruitment_general_question_categories')->insert(['jobpost_category_id' => $scope->jobpost_category_id, 'division_id' => 0, 'grade' => $scope->grade, 'question_count' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: generated categories can already contain recruitment questions.
    }
};
