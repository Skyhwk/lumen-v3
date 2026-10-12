<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jobpost_categories')) {
            return;
        }

        Schema::table('jobpost_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('jobpost_categories', 'image')) {
                $table->string('image', 500)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jobpost_categories')) {
            return;
        }

        Schema::table('jobpost_categories', function (Blueprint $table) {
            if (Schema::hasColumn('jobpost_categories', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};
