<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileCompletionToInternalAssessment extends Migration
{
    public function up()
    {
        if (Schema::hasTable('assessment_internal') && !Schema::hasColumn('assessment_internal', 'is_completed_profile')) {
            Schema::table('assessment_internal', function (Blueprint $table) {
                $table->boolean('is_completed_profile')->default(false)->after('is_link_active');
            });
        }

        if (Schema::hasTable('assessment_internal_attempts') && !Schema::hasColumn('assessment_internal_attempts', 'profile_completed_at')) {
            Schema::table('assessment_internal_attempts', function (Blueprint $table) {
                $table->dateTime('profile_completed_at')->nullable()->after('completed_at');
            });
        }
    }

    public function down()
    {
        // Non-destructive: status pengisian profil peserta dipertahankan.
    }
}
