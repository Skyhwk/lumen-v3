<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jadwal')) {
            return;
        }

        Schema::table('jadwal', function (Blueprint $table) {
            if (!Schema::hasColumn('jadwal', 'tebeng_dengan')) {
                $table->string('tebeng_dengan', 255)
                    ->nullable()
                    ->after('kendaraan')
                    ->comment('Nama tim pemilik kendaraan saat Tebeng Transportasi (note & kendaraan tidak diubah)');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jadwal')) {
            return;
        }

        Schema::table('jadwal', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal', 'tebeng_dengan')) {
                $table->dropColumn('tebeng_dengan');
            }
        });
    }
};
