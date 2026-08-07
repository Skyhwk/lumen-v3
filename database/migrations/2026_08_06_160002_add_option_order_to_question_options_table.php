<?php

use Illuminate\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOptionOrderToQuestionOptionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('question_options', 'option_order')) {
            Schema::table('question_options', function (Blueprint $table) {
                $table->unsignedInteger('option_order')->default(0)->after('is_correct');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('question_options', 'option_order')) {
            Schema::table('question_options', function (Blueprint $table) {
                $table->dropColumn('option_order');
            });
        }
    }
}