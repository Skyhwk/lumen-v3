<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixWsFinalApprovalDetailCompositeUniqueKey extends Migration
{
    public function up()
    {
        self::dropIndexIfExists('ws_final_approval_detail', 'ws_final_approval_detail_parameter_unique');

        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->unique(
                ['ws_final_approval_header_id', 'no_sampel', 'parameter_lab'],
                'ws_final_approval_detail_parameter_unique'
            );
        });
    }

    public function down()
    {
        self::dropIndexIfExists('ws_final_approval_detail', 'ws_final_approval_detail_parameter_unique');

        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->unique(
                ['ws_final_approval_header_id', 'parameter_lab'],
                'ws_final_approval_detail_parameter_unique'
            );
        });
    }

    private static function dropIndexIfExists(string $table, string $index): void
    {
        $exists = DB::selectOne(
            'SELECT COUNT(*) as total
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
             AND table_name = ?
             AND index_name = ?',
            [$table, $index]
        );

        if ($exists && (int) $exists->total > 0) {
            DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
        }
    }
}
