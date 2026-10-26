<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUnblockDetailToSamplerTrackingTroubles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            if (!Schema::hasColumn('sampler_tracking_troubles', 'reopen_reason')) {
                $table->string('reopen_reason', 80)->nullable()->after('reopen_note');
            }
            if (!Schema::hasColumn('sampler_tracking_troubles', 'sampler_follow_up_action')) {
                $table->string('sampler_follow_up_action', 80)->nullable()->after('reopen_reason');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            if (Schema::hasColumn('sampler_tracking_troubles', 'sampler_follow_up_action')) {
                $table->dropColumn('sampler_follow_up_action');
            }
            if (Schema::hasColumn('sampler_tracking_troubles', 'reopen_reason')) {
                $table->dropColumn('reopen_reason');
            }
        });
    }
}
