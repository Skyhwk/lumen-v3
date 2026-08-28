<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequirementToPersonnelRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel_requests', 'requirement')) {
                $table->text('requirement')->nullable()->after('job_description');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('personnel_requests')) {
            return;
        }

        Schema::table('personnel_requests', function (Blueprint $table) {
            if (Schema::hasColumn('personnel_requests', 'requirement')) {
                $table->dropColumn('requirement');
            }
        });
    }
}
