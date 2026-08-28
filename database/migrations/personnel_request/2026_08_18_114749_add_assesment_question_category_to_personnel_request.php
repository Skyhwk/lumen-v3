<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssesmentQuestionCategoryToPersonnelRequest extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            $table->integer('assesment_question_category')->nullable()->after('minimum_matching');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
           $table->dropColumn('assesment_question_category');
        });
    }
}
