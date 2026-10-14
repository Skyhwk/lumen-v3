<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSupportingInfoAnswersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('supporting_info_answers')) {
            return;
        }

        Schema::create('supporting_info_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('karyawan_id');
            $table->unsignedBigInteger('source_candidate_answer_id')->nullable();
            $table->unsignedBigInteger('new_recruitment_id')->nullable();
            $table->unsignedBigInteger('candidate_profile_id')->nullable();
            $table->unsignedBigInteger('question_category_id')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->string('category_name', 150)->nullable();
            $table->text('question_text')->nullable();
            $table->string('question_type', 30)->nullable();
            $table->text('answer_text')->nullable();
            $table->json('answer_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 150)->nullable();
            $table->string('updated_by', 150)->nullable();
            $table->timestamps();

            $table->unique('source_candidate_answer_id', 'uq_supporting_info_source_answer');
            $table->index('karyawan_id', 'idx_supporting_info_karyawan');
            $table->index('new_recruitment_id', 'idx_supporting_info_recruitment');
            $table->index('question_id', 'idx_supporting_info_question');
        });
    }

    public function down()
    {
        if (Schema::hasTable('supporting_info_answers')) {
            Schema::drop('supporting_info_answers');
        }
    }
}
