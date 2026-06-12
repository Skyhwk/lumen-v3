<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddApprovalFieldsToWsValueAirTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ws_value_air')) {
            return;
        }

        Schema::table('ws_value_air', function (Blueprint $table) {
            if (!Schema::hasColumn('ws_value_air', 'is_approved')) {
                $table->boolean('is_approved')->nullable()->default(0)->after('parameter');
            }
            if (!Schema::hasColumn('ws_value_air', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('is_approved');
            }
            if (!Schema::hasColumn('ws_value_air', 'approved_by')) {
                $table->string('approved_by', 255)->nullable()->after('approved_at');
            }
        });

        $this->backfillApprovalFields();
    }

    public function down()
    {
        if (!Schema::hasTable('ws_value_air')) {
            return;
        }

        Schema::table('ws_value_air', function (Blueprint $table) {
            $columns = ['is_approved', 'approved_at', 'approved_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ws_value_air', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillApprovalFields(): void
    {
        DB::statement("
            UPDATE ws_value_air w
            LEFT JOIN colorimetri c ON w.id_colorimetri = c.id
            LEFT JOIN titrimetri t ON w.id_titrimetri = t.id
            LEFT JOIN gravimetri g ON w.id_gravimetri = g.id
            LEFT JOIN subkontrak s ON w.id_subkontrak = s.id
            SET
                w.is_approved = COALESCE(c.is_approved, t.is_approved, g.is_approved, s.is_approve),
                w.approved_at = COALESCE(c.approved_at, t.approved_at, g.approved_at, s.approved_at),
                w.approved_by = COALESCE(c.approved_by, t.approved_by, g.approved_by, s.approved_by)
            WHERE COALESCE(c.id, t.id, g.id, s.id) IS NOT NULL
        ");
    }
}
