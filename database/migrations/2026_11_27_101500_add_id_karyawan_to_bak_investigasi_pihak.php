<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi_pihak')) {
            return;
        }

        Schema::table('bak_investigasi_pihak', function (Blueprint $table) {
            if (!Schema::hasColumn('bak_investigasi_pihak', 'id_karyawan')) {
                $table->unsignedInteger('id_karyawan')
                    ->nullable()
                    ->after('nama')
                    ->comment('FK master_karyawan.id');
                $table->index('id_karyawan', 'bak_inv_pihak_karyawan_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bak_investigasi_pihak')) {
            return;
        }

        Schema::table('bak_investigasi_pihak', function (Blueprint $table) {
            if (Schema::hasColumn('bak_investigasi_pihak', 'id_karyawan')) {
                $table->dropIndex('bak_inv_pihak_karyawan_idx');
                $table->dropColumn('id_karyawan');
            }
        });
    }
};
