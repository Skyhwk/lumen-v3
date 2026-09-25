<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('data_lapangan_ergonomi')) {
            return;
        }

        Schema::table('data_lapangan_ergonomi', function (Blueprint $table) {
            if (!Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_blocked');
            }
            if (!Schema::hasColumn('data_lapangan_ergonomi', 'replaced_by_id')) {
                $table->unsignedBigInteger('replaced_by_id')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('data_lapangan_ergonomi', 'koreksi_dari_id')) {
                $table->unsignedBigInteger('koreksi_dari_id')->nullable()->after('replaced_by_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('data_lapangan_ergonomi')) {
            return;
        }

        Schema::table('data_lapangan_ergonomi', function (Blueprint $table) {
            if (Schema::hasColumn('data_lapangan_ergonomi', 'koreksi_dari_id')) {
                $table->dropColumn('koreksi_dari_id');
            }
            if (Schema::hasColumn('data_lapangan_ergonomi', 'replaced_by_id')) {
                $table->dropColumn('replaced_by_id');
            }
            if (Schema::hasColumn('data_lapangan_ergonomi', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
