<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_sallary')) {
            return;
        }

        if (!Schema::hasColumn('master_sallary', 'tanggal_penggajian')) {
            Schema::table('master_sallary', function (Blueprint $table) {
                $column = $table->unsignedTinyInteger('tanggal_penggajian')
                    ->nullable()
                    ->comment('Tanggal kalender pembayaran gaji untuk supervisor, manager, dan di atasnya');
                if (Schema::hasColumn('master_sallary', 'jadwal_penggajian')) {
                    $column->after('jadwal_penggajian');
                }
            });
        }

        if (!Schema::hasColumn('master_sallary', 'penyesuaian_hari_libur')) {
            Schema::table('master_sallary', function (Blueprint $table) {
                $column = $table->string('penyesuaian_hari_libur', 20)
                    ->default('setelah')
                    ->comment('sebelum atau setelah, jika tanggal penggajian jatuh pada hari libur');
                if (Schema::hasColumn('master_sallary', 'tanggal_penggajian')) {
                    $column->after('tanggal_penggajian');
                }
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('master_sallary')) {
            return;
        }

        Schema::table('master_sallary', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'tanggal_penggajian',
                'penyesuaian_hari_libur',
            ], function ($column) {
                return Schema::hasColumn('master_sallary', $column);
            }));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
