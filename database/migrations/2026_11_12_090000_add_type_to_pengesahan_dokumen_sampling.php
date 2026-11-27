<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddTypeToPengesahanDokumenSampling extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('pengesahan_dokumen_sampling')) {
            return;
        }

        if (!Schema::hasColumn('pengesahan_dokumen_sampling', 'type')) {
            Schema::table('pengesahan_dokumen_sampling', function (Blueprint $table) {
                $table->string('type', 80)->default('sampling')->after('id');
                $table->index('type');
            });
        }

        DB::table('pengesahan_dokumen_sampling')
            ->whereNull('type')
            ->orWhere('type', '')
            ->update(['type' => 'sampling']);
    }

    public function down()
    {
        if (!Schema::hasTable('pengesahan_dokumen_sampling')) {
            return;
        }

        if (Schema::hasColumn('pengesahan_dokumen_sampling', 'type')) {
            Schema::table('pengesahan_dokumen_sampling', function (Blueprint $table) {
                $table->dropIndex(['type']);
                $table->dropColumn('type');
            });
        }
    }
}
