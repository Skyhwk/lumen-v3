<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGradeToAssessmentInternal extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('assessment_internal') || Schema::hasColumn('assessment_internal', 'grade')) {
            return;
        }

        Schema::table('assessment_internal', function (Blueprint $table) {
            $table->string('grade', 100)->nullable()->after('nama_assesment');
        });
    }

    public function down()
    {
        // Non-destructive: grade yang sudah dipakai untuk membatasi link assessment dipertahankan.
    }
}
