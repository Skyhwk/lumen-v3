<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kasbon')) {
            return;
        }

        Schema::table('kasbon', function (Blueprint $table) {
            if (!Schema::hasColumn('kasbon', 'mode_potongan')) {
                $table->enum('mode_potongan', ['auto', 'custom'])
                    ->default('auto')
                    ->after('bulan_mulai_pemotongan');
            }

            if (!Schema::hasColumn('kasbon', 'custom_potongan')) {
                $table->json('custom_potongan')
                    ->nullable()
                    ->after('nominal_potongan');
            }

            if (!Schema::hasColumn('kasbon', 'history_perubahan')) {
                $table->json('history_perubahan')
                    ->nullable()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('kasbon')) {
            return;
        }

        Schema::table('kasbon', function (Blueprint $table) {
            if (Schema::hasColumn('kasbon', 'history_perubahan')) {
                $table->dropColumn('history_perubahan');
            }
            if (Schema::hasColumn('kasbon', 'custom_potongan')) {
                $table->dropColumn('custom_potongan');
            }
            if (Schema::hasColumn('kasbon', 'mode_potongan')) {
                $table->dropColumn('mode_potongan');
            }
        });
    }
};
