<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return;
        }

        Schema::table('surat_peringatan_disipliner', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'file_qr')) {
                $table->string('file_qr', 255)->nullable()->after('file_size');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return;
        }

        Schema::table('surat_peringatan_disipliner', function (Blueprint $table) {
            if (Schema::hasColumn('surat_peringatan_disipliner', 'file_qr')) {
                $table->dropColumn('file_qr');
            }
        });
    }
};
