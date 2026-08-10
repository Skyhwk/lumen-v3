<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecruitmentInterviewsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('recruitment_interviews')) {
            Schema::create('recruitment_interviews', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('new_recruitment_id');
                $table->enum('stage', ['hrd', 'user'])->default('hrd');
                $table->dateTime('tgl_interview')->nullable();
                $table->string('jenis_interview', 50)->nullable();
                $table->string('link_gmeet', 255)->nullable();
                $table->string('ruangan_interview', 255)->nullable();
                $table->enum('status_result', ['pending', 'passed', 'failed'])->default('pending');
                $table->text('catatan_interview')->nullable();
                $table->double('nilai_interview')->nullable();
                $table->string('interviewer_by', 255)->nullable();
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();

                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recruitment_interviews');
    }
}
