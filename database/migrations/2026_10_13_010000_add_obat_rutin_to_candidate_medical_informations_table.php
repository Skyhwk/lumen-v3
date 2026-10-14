<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddObatRutinToCandidateMedicalInformationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_medical_informations')
            || Schema::hasColumn('candidate_medical_informations', 'obat_rutin')) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->text('obat_rutin')->nullable()->after('riwayat_kecelakaan');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('candidate_medical_informations')
            || !Schema::hasColumn('candidate_medical_informations', 'obat_rutin')) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->dropColumn('obat_rutin');
        });
    }
}
