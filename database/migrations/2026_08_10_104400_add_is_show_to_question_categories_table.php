<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsShowToQuestionCategoriesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('question_categories')) {
            Schema::table('question_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('question_categories', 'is_show')) {
                    $table->boolean('is_show')->default(true)->after('is_active');
                }
                if (!Schema::hasColumn('question_categories', 'duration_minutes')) {
                    $table->unsignedInteger('duration_minutes')->default(15)->after('question_count');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('question_categories')) {
            Schema::table('question_categories', function (Blueprint $table) {
                if (Schema::hasColumn('question_categories', 'is_show')) {
                    $table->dropColumn('is_show');
                }
                if (Schema::hasColumn('question_categories', 'duration_minutes')) {
                    $table->dropColumn('duration_minutes');
                }
            });
        }
    }
}
