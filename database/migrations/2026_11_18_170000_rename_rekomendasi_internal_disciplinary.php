<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        if (Schema::hasColumn('bak_investigasi', 'rekomendasi_alur')) {
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY rekomendasi_alur ENUM('internal_hr_direv', 'internal_disciplinary', 'komite_manajemen') NULL"
            );
            DB::table('bak_investigasi')
                ->where('rekomendasi_alur', 'internal_hr_direv')
                ->update(['rekomendasi_alur' => 'internal_disciplinary']);
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY rekomendasi_alur ENUM('internal_disciplinary', 'komite_manajemen') NULL"
            );
        }

        if (Schema::hasColumn('bak_investigasi', 'tindak_lanjut_awal')) {
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY tindak_lanjut_awal ENUM('internal_hr_direv', 'internal_disciplinary', 'komite_manajemen', 'tanpa_sanksi') NULL"
            );
            DB::table('bak_investigasi')
                ->where('tindak_lanjut_awal', 'internal_hr_direv')
                ->update(['tindak_lanjut_awal' => 'internal_disciplinary']);
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY tindak_lanjut_awal ENUM('internal_disciplinary', 'komite_manajemen', 'tanpa_sanksi') NULL"
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        if (Schema::hasColumn('bak_investigasi', 'rekomendasi_alur')) {
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY rekomendasi_alur ENUM('internal_hr_direv', 'internal_disciplinary', 'komite_manajemen') NULL"
            );
            DB::table('bak_investigasi')
                ->where('rekomendasi_alur', 'internal_disciplinary')
                ->update(['rekomendasi_alur' => 'internal_hr_direv']);
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY rekomendasi_alur ENUM('internal_hr_direv', 'komite_manajemen') NULL"
            );
        }

        if (Schema::hasColumn('bak_investigasi', 'tindak_lanjut_awal')) {
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY tindak_lanjut_awal ENUM('internal_hr_direv', 'internal_disciplinary', 'komite_manajemen', 'tanpa_sanksi') NULL"
            );
            DB::table('bak_investigasi')
                ->where('tindak_lanjut_awal', 'internal_disciplinary')
                ->update(['tindak_lanjut_awal' => 'internal_hr_direv']);
            DB::statement(
                "ALTER TABLE bak_investigasi MODIFY tindak_lanjut_awal ENUM('internal_hr_direv', 'komite_manajemen', 'tanpa_sanksi') NULL"
            );
        }
    }
};