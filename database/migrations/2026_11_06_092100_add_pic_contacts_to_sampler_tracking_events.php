<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sampler_tracking_events')) {
            return;
        }

        if (!Schema::hasColumn('sampler_tracking_events', 'pic_contacts')) {
            Schema::table('sampler_tracking_events', function (Blueprint $table) {
                $table->json('pic_contacts')->nullable()->after('note');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sampler_tracking_events') && Schema::hasColumn('sampler_tracking_events', 'pic_contacts')) {
            Schema::table('sampler_tracking_events', function (Blueprint $table) {
                $table->dropColumn('pic_contacts');
            });
        }
    }
};
