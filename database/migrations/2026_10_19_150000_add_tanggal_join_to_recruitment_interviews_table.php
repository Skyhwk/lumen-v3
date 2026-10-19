<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('recruitment_interviews')
            || Schema::hasColumn('recruitment_interviews', 'tanggal_join')
        ) {
            return;
        }

        Schema::table('recruitment_interviews', function (Blueprint $table) {
            $table->date('tanggal_join')->nullable()->after('tgl_interview');
        });
    }

    public function down(): void
    {
        if (
            !Schema::hasTable('recruitment_interviews')
            || !Schema::hasColumn('recruitment_interviews', 'tanggal_join')
        ) {
            return;
        }

        Schema::table('recruitment_interviews', function (Blueprint $table) {
            $table->dropColumn('tanggal_join');
        });
    }
};
