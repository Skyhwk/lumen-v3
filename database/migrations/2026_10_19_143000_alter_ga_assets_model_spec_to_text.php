<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_assets') || !Schema::hasColumn('ga_assets', 'model_spec')) {
            return;
        }

        DB::statement('ALTER TABLE ga_assets MODIFY model_spec TEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('ga_assets') || !Schema::hasColumn('ga_assets', 'model_spec')) {
            return;
        }

        DB::statement('ALTER TABLE ga_assets MODIFY model_spec VARCHAR(255) NULL');
    }
};
