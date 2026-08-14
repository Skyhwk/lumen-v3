<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateOnboardingVerificationTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_onboarding_verification')) {
            Schema::create('candidate_onboarding_verification', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('new_recruitment_id')->unique();
                $table->boolean('has_id_card')->default(false);
                $table->boolean('has_email')->default(false);
                $table->boolean('has_server_account')->default(false);
                $table->boolean('has_all_documents')->default(false);
                $table->string('verified_by', 255)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('employee_migrated_at')->nullable();
                $table->string('employee_migrated_by', 255)->nullable();
                $table->timestamps();

                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('candidate_onboarding_verification')) {
            Schema::drop('candidate_onboarding_verification');
        }
    }
}
