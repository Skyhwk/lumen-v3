<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDivisiBagianToDraftingKebijakanTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('drafting_kebijakan')) {
            return;
        }

        Schema::table('drafting_kebijakan', function (Blueprint $table) {
            if (!Schema::hasColumn('drafting_kebijakan', 'divisi_bagian')) {
                $table->string('divisi_bagian', 255)->nullable()->after('request_kebijakan_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('drafting_kebijakan')) {
            return;
        }

        Schema::table('drafting_kebijakan', function (Blueprint $table) {
            if (Schema::hasColumn('drafting_kebijakan', 'divisi_bagian')) {
                $table->dropColumn('divisi_bagian');
            }
        });
    }
}
