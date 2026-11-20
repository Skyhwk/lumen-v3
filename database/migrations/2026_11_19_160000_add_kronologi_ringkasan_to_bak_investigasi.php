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

        if (!Schema::hasColumn('bak_investigasi', 'kronologi_ringkasan')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->longText('kronologi_ringkasan')
                    ->nullable()
                    ->after('jenis_bukti')
                    ->comment('Naratif ringkasan kronologi sebelum rincian 5W+1H');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bak_investigasi') && Schema::hasColumn('bak_investigasi', 'kronologi_ringkasan')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->dropColumn('kronologi_ringkasan');
            });
        }
    }
};