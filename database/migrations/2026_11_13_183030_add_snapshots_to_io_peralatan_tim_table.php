<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail setiap perubahan bawaan aktual peralatan tim.
 * Append-only JSON: siapa mengubah, kapan, apa yang ditambah/dikurangi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('io_peralatan_tim')) {
            return;
        }
        if (Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
            return;
        }

        Schema::table('io_peralatan_tim', function (Blueprint $table) {
            $table->json('snapshots')->nullable()->after('peralatan');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('io_peralatan_tim') || !Schema::hasColumn('io_peralatan_tim', 'snapshots')) {
            return;
        }

        Schema::table('io_peralatan_tim', function (Blueprint $table) {
            $table->dropColumn('snapshots');
        });
    }
};
