<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        Schema::table('bak_investigasi', function (Blueprint $table) {
            if (!Schema::hasColumn('bak_investigasi', 'sanksi_surat_teguran_id')) {
                $table->unsignedBigInteger('sanksi_surat_teguran_id')->nullable()->after('sanksi_st_detail');
            }
            if (!Schema::hasColumn('bak_investigasi', 'sanksi_surat_peringatan_id')) {
                $table->unsignedBigInteger('sanksi_surat_peringatan_id')->nullable()->after('sanksi_sp_detail');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        Schema::table('bak_investigasi', function (Blueprint $table) {
            foreach (['sanksi_surat_teguran_id', 'sanksi_surat_peringatan_id'] as $col) {
                if (Schema::hasColumn('bak_investigasi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};