<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pencadangan_upah', 'perubahan_log')) {
            Schema::table('pencadangan_upah', function (Blueprint $table) {
                $table->json('perubahan_log')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pencadangan_upah', 'perubahan_log')) {
            Schema::table('pencadangan_upah', function (Blueprint $table) {
                $table->dropColumn('perubahan_log');
            });
        }
    }
};
