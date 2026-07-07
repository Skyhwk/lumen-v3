<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('qsd_revenue_snapshot')) {
            DB::statement('ALTER TABLE qsd_revenue_snapshot MODIFY uuid VARCHAR(512) NOT NULL');
        }

        if (Schema::hasTable('qsd_forecast_snapshot')) {
            DB::statement('ALTER TABLE qsd_forecast_snapshot MODIFY uuid VARCHAR(512) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('qsd_revenue_snapshot')) {
            DB::statement('ALTER TABLE qsd_revenue_snapshot MODIFY uuid VARCHAR(64) NOT NULL');
        }

        if (Schema::hasTable('qsd_forecast_snapshot')) {
            DB::statement('ALTER TABLE qsd_forecast_snapshot MODIFY uuid VARCHAR(64) NOT NULL');
        }
    }
};
