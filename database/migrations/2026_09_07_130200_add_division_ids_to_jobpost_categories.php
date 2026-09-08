<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jobpost_categories') || Schema::hasColumn('jobpost_categories', 'division_ids')) return;
        Schema::table('jobpost_categories', function (Blueprint $table) {
            $table->json('division_ids')->nullable()->after('assigned_user_ids');
        });
    }

    public function down(): void
    {
        // Non-destructive: active Jobpost Category configuration may already use this column.
    }
};
