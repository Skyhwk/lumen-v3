<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateSupportingInfoAnswersTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('candidate_supporting_info_answers')) {
            return;
        }

        Schema::create('candidate_supporting_info_answers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('candidate_profile_id');
            $table->unsignedBigInteger('new_recruitment_id');
            $table->unsignedBigInteger('question_category_id')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->string('category_name', 150)->nullable();
            $table->text('question_text')->nullable();
            $table->string('question_type', 30)->nullable();
            $table->text('answer_text')->nullable();
            $table->json('answer_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 150)->nullable();
            $table->timestamps();

            $table->index('candidate_profile_id', 'idx_candidate_supporting_profile');
            $table->index('new_recruitment_id', 'idx_candidate_supporting_recruitment');
            $table->index('question_id', 'idx_candidate_supporting_question');
        });
    }

    public function down()
    {
        Schema::dropIfExists('candidate_supporting_info_answers');
    }
}
