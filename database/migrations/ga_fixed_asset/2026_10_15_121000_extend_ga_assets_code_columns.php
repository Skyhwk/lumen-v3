<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_assets')) {
            return;
        }

        DB::statement('ALTER TABLE ga_assets MODIFY asset_code VARCHAR(80) NOT NULL');
        DB::statement('ALTER TABLE ga_assets MODIFY cs_code VARCHAR(80) NOT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ga_assets')) {
            return;
        }

        DB::statement('ALTER TABLE ga_assets MODIFY asset_code VARCHAR(40) NOT NULL');
        DB::statement('ALTER TABLE ga_assets MODIFY cs_code VARCHAR(40) NOT NULL');
    }
};
