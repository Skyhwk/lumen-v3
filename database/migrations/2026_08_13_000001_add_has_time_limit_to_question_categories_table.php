<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHasTimeLimitToQuestionCategoriesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('question_categories')) {
            Schema::table('question_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('question_categories', 'has_time_limit')) {
                    $table->boolean('has_time_limit')->default(true)->after('duration_minutes');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('question_categories')) {
            Schema::table('question_categories', function (Blueprint $table) {
                if (Schema::hasColumn('question_categories', 'has_time_limit')) {
                    $table->dropColumn('has_time_limit');
                }
            });
        }
    }
}
