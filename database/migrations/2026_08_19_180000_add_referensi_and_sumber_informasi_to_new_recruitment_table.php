<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReferensiAndSumberInformasiToNewRecruitmentTable extends Migration
{
    public function up()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (!Schema::hasColumn('new_recruitment', 'referensi')) {
                $table->json('referensi')->nullable()->after('pengalaman_kerja');
            }
            if (!Schema::hasColumn('new_recruitment', 'sumber_informasi')) {
                $table->string('sumber_informasi', 150)->nullable()->after('referensi');
            }
        });
    }

    public function down()
    {
        Schema::table('new_recruitment', function (Blueprint $table) {
            if (Schema::hasColumn('new_recruitment', 'sumber_informasi')) {
                $table->dropColumn('sumber_informasi');
            }
            if (Schema::hasColumn('new_recruitment', 'referensi')) {
                $table->dropColumn('referensi');
            }
        });
    }
}
