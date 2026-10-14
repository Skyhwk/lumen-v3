<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_shift_karyawan')) {
            return;
        }

        Schema::table('master_shift_karyawan', function (Blueprint $table) {
            if (!Schema::hasColumn('master_shift_karyawan', 'created_by')) {
                $table->string('created_by', 150)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('master_shift_karyawan', 'updated_by')) {
                $table->string('updated_by', 150)->nullable()->after('created_by');
            }
        });

        DB::table('master_shift_karyawan')
            ->whereNull('created_by')
            ->update(['created_by' => 'system']);

        DB::table('master_shift_karyawan')
            ->whereNull('updated_by')
            ->update(['updated_by' => 'system']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('master_shift_karyawan')) {
            return;
        }

        Schema::table('master_shift_karyawan', function (Blueprint $table) {
            if (Schema::hasColumn('master_shift_karyawan', 'updated_by')) {
                $table->dropColumn('updated_by');
            }
            if (Schema::hasColumn('master_shift_karyawan', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
