<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsCompletedToPersonnelRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel_requests', 'is_completed')) {
                $table->boolean('is_completed')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('personnel_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('is_completed');
            }
            if (!Schema::hasColumn('personnel_requests', 'completed_by')) {
                $table->string('completed_by', 255)->nullable()->after('completed_at');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            foreach (['completed_by', 'completed_at', 'is_completed'] as $column) {
                if (Schema::hasColumn('personnel_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
