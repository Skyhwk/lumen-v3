<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddJumlahTanggunganToCandidateProfilesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_profiles')) {
            return;
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('candidate_profiles', 'jumlah_tanggungan')) {
                $table->unsignedTinyInteger('jumlah_tanggungan')
                    ->nullable()
                    ->after('status_tempat_tinggal');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('candidate_profiles')) {
            return;
        }

        Schema::table('candidate_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('candidate_profiles', 'jumlah_tanggungan')) {
                $table->dropColumn('jumlah_tanggungan');
            }
        });
    }
}
