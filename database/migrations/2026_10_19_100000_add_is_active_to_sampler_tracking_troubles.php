<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsActiveToSamplerTrackingTroubles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')
            || Schema::hasColumn('sampler_tracking_troubles', 'is_active')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            $table->boolean('is_active')->default(1)->after('activity_date');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')
            || !Schema::hasColumn('sampler_tracking_troubles', 'is_active')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
}
