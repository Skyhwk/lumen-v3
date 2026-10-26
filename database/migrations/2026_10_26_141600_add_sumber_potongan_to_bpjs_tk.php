<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('bpjs_tk', 'sumber_potongan')) {
            Schema::table('bpjs_tk', function (Blueprint $table) {
                $table->string('sumber_potongan', 20)
                    ->default('gaji_pokok')
                    ->after('nominal_potongan_karyawan');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bpjs_tk', 'sumber_potongan')) {
            Schema::table('bpjs_tk', function (Blueprint $table) {
                $table->dropColumn('sumber_potongan');
            });
        }
    }
};
