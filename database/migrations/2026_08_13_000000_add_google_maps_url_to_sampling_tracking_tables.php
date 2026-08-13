<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleMapsUrlToSamplingTrackingTables extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sampling_plan') && !Schema::hasColumn('sampling_plan', 'google_maps_url')) {
            Schema::table('sampling_plan', function (Blueprint $table) {
                $table->string('google_maps_url', 1000)->nullable()->after('keterangan_lain');
            });
        }

        if (Schema::hasTable('sampler_tracking_sessions') && !Schema::hasColumn('sampler_tracking_sessions', 'google_maps_url')) {
            Schema::table('sampler_tracking_sessions', function (Blueprint $table) {
                $table->string('google_maps_url', 1000)->nullable()->after('alamat_sampling');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('sampler_tracking_sessions') && Schema::hasColumn('sampler_tracking_sessions', 'google_maps_url')) {
            Schema::table('sampler_tracking_sessions', function (Blueprint $table) {
                $table->dropColumn('google_maps_url');
            });
        }

        if (Schema::hasTable('sampling_plan') && Schema::hasColumn('sampling_plan', 'google_maps_url')) {
            Schema::table('sampling_plan', function (Blueprint $table) {
                $table->dropColumn('google_maps_url');
            });
        }
    }
}
