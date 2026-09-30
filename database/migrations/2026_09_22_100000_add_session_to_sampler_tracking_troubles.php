<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSessionToSamplerTrackingTroubles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('sampler_tracking_troubles') || Schema::hasColumn('sampler_tracking_troubles', 'tracking_session_id')) return;
        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            // Legacy development rows remain unchanged and can be cleared manually.
            $table->unsignedBigInteger('tracking_session_id')->nullable();
            $table->dropUnique('tracking_trouble_sampler_date');
            $table->unique(['sampler_id', 'tracking_session_id'], 'tracking_trouble_sampler_session');
        });
    }

    public function down()
    {
        if (!Schema::hasColumn('sampler_tracking_troubles', 'tracking_session_id')) return;
        if (DB::table('sampler_tracking_troubles')->select('sampler_id', 'activity_date')
            ->groupBy('sampler_id', 'activity_date')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback tidak dapat menggabungkan beberapa kendala per tanggal. Bersihkan data dev terlebih dahulu.');
        }
        Schema::table('sampler_tracking_troubles', function (Blueprint $table) {
            $table->dropUnique('tracking_trouble_sampler_session');
            $table->dropColumn('tracking_session_id');
            $table->unique(['sampler_id', 'activity_date'], 'tracking_trouble_sampler_date');
        });
    }
}
