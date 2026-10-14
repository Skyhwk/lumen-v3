<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGangguanMentalToCandidateMedicalInformationsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('candidate_medical_informations')
            || Schema::hasColumn('candidate_medical_informations', 'gangguan_mental')) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->text('gangguan_mental')->nullable()->after('obat_rutin');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('candidate_medical_informations')
            || !Schema::hasColumn('candidate_medical_informations', 'gangguan_mental')) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->dropColumn('gangguan_mental');
        });
    }
}
