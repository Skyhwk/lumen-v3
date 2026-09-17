<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSamplerTrackingTroubles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')) {
            Schema::create('sampler_tracking_troubles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('sampler_id', 70);
                $table->date('activity_date');
                $table->boolean('is_clear')->default(0);
                $table->timestamp('cleared_at')->nullable();
                $table->unsignedBigInteger('reopened_by')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->text('reopen_note')->nullable();
                $table->timestamps();
                $table->unique(['sampler_id', 'activity_date'], 'tracking_trouble_sampler_date');
                $table->index(['sampler_id', 'is_clear'], 'tracking_trouble_unresolved');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('sampler_tracking_troubles');
    }
}
