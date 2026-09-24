<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSamplingKeysToMobilisasiOperasionalDetail extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            return;
        }

        Schema::table('mobilisasi_operasional_detail', function (Blueprint $table) {
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'id_sampling')) {
                $table->unsignedBigInteger('id_sampling')->nullable()->index()->after('id_jadwal');
            }
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'userid')) {
                $table->unsignedBigInteger('userid')->nullable()->index()->after('id_sampling');
            }
            if (!Schema::hasColumn('mobilisasi_operasional_detail', 'parsial')) {
                $table->unsignedBigInteger('parsial')->nullable()->index()->after('userid');
            }
        });

        if (
            Schema::hasColumn('mobilisasi_operasional_detail', 'id_sampling')
            && Schema::hasTable('jadwal')
        ) {
            DB::statement('
                UPDATE mobilisasi_operasional_detail d
                INNER JOIN jadwal j ON j.id = d.id_jadwal
                SET
                    d.id_sampling = j.id_sampling,
                    d.userid = j.userid,
                    d.parsial = j.parsial
                WHERE d.id_sampling IS NULL
            ');
        }
    }

    public function down()
    {
        if (!Schema::hasTable('mobilisasi_operasional_detail')) {
            return;
        }

        Schema::table('mobilisasi_operasional_detail', function (Blueprint $table) {
            foreach (['parsial', 'userid', 'id_sampling'] as $column) {
                if (Schema::hasColumn('mobilisasi_operasional_detail', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
