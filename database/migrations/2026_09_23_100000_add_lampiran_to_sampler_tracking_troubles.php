<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLampiranToSamplerTrackingTroubles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_troubles')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            if (!Schema::hasColumn('sampler_tracking_troubles', 'lampiran')) {
                $table->json('lampiran')->nullable()->after('sampler_follow_up_action');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('sampler_tracking_troubles') || !Schema::hasColumn('sampler_tracking_troubles', 'lampiran')) {
            return;
        }

        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            $table->dropColumn('lampiran');
        });
    }
}
