<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManualFlagsToKebijakanDokumen extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (!Schema::hasColumn('kebijakan_dokumen', 'is_manual')) {
                $table->boolean('is_manual')->default(false)->after('is_active');
            }

            if (!Schema::hasColumn('kebijakan_dokumen', 'manual_created_by')) {
                $table->unsignedBigInteger('manual_created_by')->nullable()->after('is_manual');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (Schema::hasColumn('kebijakan_dokumen', 'manual_created_by')) {
                $table->dropColumn('manual_created_by');
            }

            if (Schema::hasColumn('kebijakan_dokumen', 'is_manual')) {
                $table->dropColumn('is_manual');
            }
        });
    }
}
