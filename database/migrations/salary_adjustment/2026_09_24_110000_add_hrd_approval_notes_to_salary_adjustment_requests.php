<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('salary_adjustment_requests', 'hrd_approval_notes')) {
                $table->text('hrd_approval_notes')->nullable()->after('hrd_final_adjustment_notes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('salary_adjustment_requests', 'hrd_approval_notes')) {
                $table->dropColumn('hrd_approval_notes');
            }
        });
    }
};
