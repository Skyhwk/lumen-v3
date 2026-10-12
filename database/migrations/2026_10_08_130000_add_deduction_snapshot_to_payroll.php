<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payroll', 'deduction_snapshot')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->json('deduction_snapshot')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll', 'deduction_snapshot')) {
            Schema::table('payroll', function (Blueprint $table) {
                $table->dropColumn('deduction_snapshot');
            });
        }
    }
};
