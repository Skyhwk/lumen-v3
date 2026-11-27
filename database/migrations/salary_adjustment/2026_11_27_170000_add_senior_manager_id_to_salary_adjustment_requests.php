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
            if (!Schema::hasColumn('salary_adjustment_requests', 'senior_manager_id')) {
                $table->unsignedBigInteger('senior_manager_id')->nullable()->after('requested_by_id');
                $table->index('senior_manager_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('salary_adjustment_requests')) {
            return;
        }

        Schema::table('salary_adjustment_requests', function (Blueprint $table) {
            if (Schema::hasColumn('salary_adjustment_requests', 'senior_manager_id')) {
                $table->dropIndex(['senior_manager_id']);
                $table->dropColumn('senior_manager_id');
            }
        });
    }
};
