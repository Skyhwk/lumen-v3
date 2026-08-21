<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTokenToAssessmentInternalTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal') || Schema::hasColumn('assessment_internal', 'token')) {
            return;
        }

        Schema::table('assessment_internal', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->unique()->after('nama_assesment');
        });
    }

    public function down()
    {
        // Non-destructive: token link produksi tidak dihapus otomatis.
    }
}
