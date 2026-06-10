<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddParameterToWsValueAirTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ws_value_air')) {
            return;
        }

        if (!Schema::hasColumn('ws_value_air', 'parameter')) {
            Schema::table('ws_value_air', function (Blueprint $table) {
                $table->string('parameter', 255)->nullable()->after('no_sampel');
            });
        }

        $this->backfillParameter();
    }

    public function down()
    {
        if (Schema::hasTable('ws_value_air') && Schema::hasColumn('ws_value_air', 'parameter')) {
            Schema::table('ws_value_air', function (Blueprint $table) {
                $table->dropColumn('parameter');
            });
        }
    }

    private function backfillParameter(): void
    {
        DB::statement("
            UPDATE ws_value_air w
            LEFT JOIN colorimetri c ON w.id_colorimetri = c.id
            LEFT JOIN titrimetri t ON w.id_titrimetri = t.id
            LEFT JOIN gravimetri g ON w.id_gravimetri = g.id
            LEFT JOIN subkontrak s ON w.id_subkontrak = s.id
            SET w.parameter = COALESCE(c.parameter, t.parameter, g.parameter, s.parameter)
            WHERE (w.parameter IS NULL OR w.parameter = '')
              AND COALESCE(c.parameter, t.parameter, g.parameter, s.parameter) IS NOT NULL
        ");
    }
}
