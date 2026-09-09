<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecruitmentApplicationDraftsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('recruitment_application_drafts')) {
            return;
        }

        Schema::create('recruitment_application_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('draft_token')->unique();
            $table->unsignedBigInteger('personnel_request_id')->nullable();
            $table->string('no_request', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('no_telepon', 20)->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('max_step_reached')->default(1);
            $table->json('form_data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('no_telepon');
            $table->index(['no_request', 'personnel_request_id'], 'recruitment_drafts_request_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('recruitment_application_drafts');
    }
}
