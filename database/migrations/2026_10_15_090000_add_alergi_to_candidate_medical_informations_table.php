<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('candidate_medical_informations')
            || Schema::hasColumn('candidate_medical_informations', 'alergi')
        ) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->text('alergi')->nullable()->after('gangguan_mental');
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('candidate_medical_informations')
            || !Schema::hasColumn('candidate_medical_informations', 'alergi')
        ) {
            return;
        }

        Schema::table('candidate_medical_informations', function (Blueprint $table) {
            $table->dropColumn('alergi');
        });
    }
};
