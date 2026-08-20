<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRequirementToPersonnelRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel_requests', 'requirement')) {
                $table->text('requirement')->nullable()->after('divisi_alias');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('personnel_requests', function (Blueprint $table) {
            if (Schema::hasColumn('personnel_requests', 'requirement')) {
                $table->dropColumn('requirement');
            }
        });
    }
}
