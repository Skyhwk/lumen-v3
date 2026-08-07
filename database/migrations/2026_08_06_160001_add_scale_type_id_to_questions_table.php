<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddScaleTypeIdToQuestionsTable extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('scale_type_id')->nullable()->after('question_type');
            $table->foreign('scale_type_id', 'questions_scale_type_id_fk')
                ->references('id')
                ->on('scale_types')
                ->nullOnDelete();
            $table->index('scale_type_id', 'idx_questions_scale_type_id');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropForeign('questions_scale_type_id_fk');
            $table->dropIndex('idx_questions_scale_type_id');
            $table->dropColumn('scale_type_id');
        });
    }
}