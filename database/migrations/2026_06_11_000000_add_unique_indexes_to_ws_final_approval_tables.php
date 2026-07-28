<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexesToWsFinalApprovalTables extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('ws_final_approval_detail', 'parameter_lab')) {
            Schema::table('ws_final_approval_detail', function (Blueprint $table) {
                $table->string('parameter_lab', 70)->nullable()->after('no_sampel');
            });

            if (Schema::hasColumn('ws_final_approval_detail', 'parameter')) {
                DB::table('ws_final_approval_detail')
                    ->whereNull('parameter_lab')
                    ->update(['parameter_lab' => DB::raw('parameter')]);
            }
        }

        if (!Schema::hasColumn('ws_final_approval_detail', 'parameter_regulasi')) {
            Schema::table('ws_final_approval_detail', function (Blueprint $table) {
                $table->string('parameter_regulasi', 100)->nullable()->after('parameter_lab');
            });
        }

        DB::table('ws_final_approval_detail')
            ->whereNull('parameter_regulasi')
            ->update(['parameter_regulasi' => '']);

        Schema::table('ws_final_approval_header', function (Blueprint $table) {
            $table->unique('no_sampel', 'ws_final_approval_header_no_sampel_unique');
        });

        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->unique(
                ['ws_final_approval_header_id', 'parameter_lab', 'parameter_regulasi'],
                'ws_final_approval_detail_parameter_unique'
            );
        });
    }

    public function down()
    {
        Schema::table('ws_final_approval_detail', function (Blueprint $table) {
            $table->dropUnique('ws_final_approval_detail_parameter_unique');
        });

        Schema::table('ws_final_approval_header', function (Blueprint $table) {
            $table->dropUnique('ws_final_approval_header_no_sampel_unique');
        });
    }
}
