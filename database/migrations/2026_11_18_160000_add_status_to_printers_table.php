<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToPrintersTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('printers')) {
            return;
        }

        if (!Schema::hasColumn('printers', 'status')) {
            Schema::table('printers', function (Blueprint $table) {
                $table->string('status', 64)->nullable()->after('full_path');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('printers') && Schema::hasColumn('printers', 'status')) {
            Schema::table('printers', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
}