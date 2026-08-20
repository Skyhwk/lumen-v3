<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsAgreedToCandidateProfilesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_profiles')) {
            return;
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('candidate_profiles', 'is_agreed')) {
                $table->boolean('is_agreed')
                    ->default(false)
                    ->after('no_telepon_darurat_2');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('candidate_profiles')) {
            return;
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('candidate_profiles', 'is_agreed')) {
                $table->dropColumn('is_agreed');
            }
        });
    }
}
