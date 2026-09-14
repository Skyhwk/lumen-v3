<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQrPayloadToKebijakanDokumen extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (!Schema::hasColumn('kebijakan_dokumen', 'qr_payload')) {
                $table->longText('qr_payload')->nullable()->after('pdf_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kebijakan_dokumen')) {
            return;
        }

        Schema::table('kebijakan_dokumen', function (Blueprint $table) {
            if (Schema::hasColumn('kebijakan_dokumen', 'qr_payload')) {
                $table->dropColumn('qr_payload');
            }
        });
    }
}
