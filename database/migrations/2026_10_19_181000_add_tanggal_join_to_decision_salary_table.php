<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('decision_salary') || Schema::hasColumn('decision_salary', 'tanggal_join')) {
            return;
        }

        Schema::table('decision_salary', function (Blueprint $table) {
            $table->date('tanggal_join')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('decision_salary') || !Schema::hasColumn('decision_salary', 'tanggal_join')) {
            return;
        }

        Schema::table('decision_salary', function (Blueprint $table) {
            $table->dropColumn('tanggal_join');
        });
    }
};
