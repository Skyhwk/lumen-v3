<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixWsFinalApprovalDetailUniqueKey extends Migration
{
    public function up()
    {
        $headerGroups = DB::table('ws_final_approval_header')
            ->select('no_sampel', DB::raw('MIN(id) as keep_id'))
            ->whereNotNull('no_sampel')
            ->groupBy('no_sampel')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($headerGroups as $headerGroup) {
            $headerIds = DB::table('ws_final_approval_header')
                ->where('no_sampel', $headerGroup->no_sampel)
                ->pluck('id');

            $details = DB::table('ws_final_approval_detail')
                ->whereIn('ws_final_approval_header_id', $headerIds)
                ->orderBy('id')
                ->get();

            foreach ($details as $detail) {
                DB::table('ws_final_approval_detail')->updateOrInsert([
                    'ws_final_approval_header_id' => $headerGroup->keep_id,
                    'parameter_lab' => $detail->parameter_lab,
                ], [
                    'no_sampel' => $detail->no_sampel,
                    'parameter_regulasi' => $detail->parameter_regulasi,
                    'hasil' => $detail->hasil,
                ]);
            }

            DB::table('ws_final_approval_detail')
                ->whereIn('ws_final_approval_header_id', $headerIds)
                ->where('ws_final_approval_header_id', '!=', $headerGroup->keep_id)
                ->delete();

            DB::table('ws_final_approval_header')
                ->whereIn('id', $headerIds)
                ->where('id', '!=', $headerGroup->keep_id)
                ->delete();
        }

        self::dropIndexIfExists('ws_final_approval_header', 'ws_final_approval_header_no_sampel_unique');
        self::dropIndexIfExists('ws_final_approval_detail', 'ws_final_approval_detail_parameter_unique');

        Schema::table('ws_final_approval_header', function (Blueprint $table) {
            $table->unique('no_sampel', 'ws_final_approval_header_no_sampel_unique');
        });

        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->unique(
                ['ws_final_approval_header_id', 'parameter_lab'],
                'ws_final_approval_detail_parameter_unique'
            );
        });
    }

    public function down()
    {
        self::dropIndexIfExists('ws_final_approval_header', 'ws_final_approval_header_no_sampel_unique');
        self::dropIndexIfExists('ws_final_approval_detail', 'ws_final_approval_detail_parameter_unique');

        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->unique(
                ['ws_final_approval_header_id', 'parameter_lab', 'parameter_regulasi'],
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
