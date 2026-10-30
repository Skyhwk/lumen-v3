<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMetaHistoryToSamplerTrackingSessions extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_sessions')) {
            return;
        }

        if (!Schema::hasColumn('sampler_tracking_sessions', 'meta_history')) {
            Schema::table('sampler_tracking_sessions', function (Blueprint $table) {
                $table->longText('meta_history')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('sampler_tracking_sessions')
            && Schema::hasColumn('sampler_tracking_sessions', 'meta_history')) {
            Schema::table('sampler_tracking_sessions', function (Blueprint $table) {
                $table->dropColumn('meta_history');
            });
        }
    }
}
