<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSamplerTrackingActivityOpens extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sampler_tracking_activity_opens')) {
            return;
        }

        Schema::create('sampler_tracking_activity_opens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sampler_id');
            $table->unsignedBigInteger('target_session_id');
            $table->unsignedBigInteger('target_member_id')->nullable();
            $table->unsignedBigInteger('blocking_session_id')->nullable();
            $table->unsignedBigInteger('opened_by')->nullable();
            $table->text('open_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index(['sampler_id', 'target_session_id', 'is_active'], 'idx_st_activity_opens_sampler_target');
            $table->index(['target_session_id', 'is_active'], 'idx_st_activity_opens_target_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sampler_tracking_activity_opens');
    }
}
