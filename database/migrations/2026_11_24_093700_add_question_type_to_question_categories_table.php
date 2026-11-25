<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQuestionTypeToQuestionCategoriesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('question_categories') && !Schema::hasColumn('question_categories', 'question_type')) {
            Schema::table('question_categories', function (Blueprint $table) {
                $table->string('question_type', 30)->nullable()->after('name');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('question_categories') && Schema::hasColumn('question_categories', 'question_type')) {
            Schema::table('question_categories', function (Blueprint $table) {
                $table->dropColumn('question_type');
            });
        }
    }
}
