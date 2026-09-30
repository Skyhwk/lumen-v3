<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRekomendasiToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment') || Schema::hasColumn('new_recruitment', 'rekomendasi')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            $table->json('rekomendasi')->nullable()->after('referensi');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment') || !Schema::hasColumn('new_recruitment', 'rekomendasi')) {
            return;
        }

        Schema::table('new_recruitment', function (Blueprint $table) {
            $table->dropColumn('rekomendasi');
        });
    }
}
