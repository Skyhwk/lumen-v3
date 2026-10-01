<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sallary_offer')) return;
        Schema::table('sallary_offer', function (Blueprint $table) {
            if (!Schema::hasColumn('sallary_offer', 'requester_salary_status')) {
                $table->string('requester_salary_status', 32)->nullable();
            }
            if (!Schema::hasColumn('sallary_offer', 'requester_salary_decided_at')) {
                $table->timestamp('requester_salary_decided_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sallary_offer')) return;
        Schema::table('sallary_offer', function (Blueprint $table) {
            foreach (['requester_salary_status', 'requester_salary_decided_at'] as $column) {
                if (Schema::hasColumn('sallary_offer', $column)) $table->dropColumn($column);
            }
        });
    }
};
