<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLegalIssueToNewRecruitmentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        if (!Schema::hasColumn('new_recruitment', 'masalah_hukum')) {
            Schema::table('new_recruitment', function (Blueprint $table) {
                $table->json('masalah_hukum')->nullable()->after('rekomendasi');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('new_recruitment')) {
            return;
        }

        if (Schema::hasColumn('new_recruitment', 'masalah_hukum')) {
            Schema::table('new_recruitment', function (Blueprint $table) {
                $table->dropColumn('masalah_hukum');
            });
        }
    }
}
