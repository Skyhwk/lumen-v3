<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateQuestionCategoriesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('question_categories')) {
            Schema::create('question_categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 150)->unique();
                $table->unsignedInteger('question_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 150)->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('questions') && !Schema::hasColumn('questions', 'question_category_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->unsignedBigInteger('question_category_id')->nullable()->after('id');
                $table->foreign('question_category_id', 'questions_question_category_id_fk')
                    ->references('id')->on('question_categories')->nullOnDelete();
                $table->index('question_category_id', 'idx_questions_question_category_id');
            });
        }

        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'category')) {
            DB::table('questions')->select('category')->whereNotNull('category')->where('category', '<>', '')->distinct()->orderBy('category')->get()->each(function ($question) {
                $questionCount = DB::table('questions')->where('category', $question->category)->count();
                $categoryId = DB::table('question_categories')->insertGetId([
                    'name' => $question->category,
                    'question_count' => $questionCount,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('questions')->where('category', $question->category)->update(['question_category_id' => $categoryId]);
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('questions') && Schema::hasColumn('questions', 'question_category_id')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropForeign('questions_question_category_id_fk');
                $table->dropIndex('idx_questions_question_category_id');
                $table->dropColumn('question_category_id');
            });
        }

        Schema::dropIfExists('question_categories');
    }
}
