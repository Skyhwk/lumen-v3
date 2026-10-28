<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsBackdateToRequestQuotationTables extends Migration
{
    public function up()
    {
        // 1. Tambah is_backdate di request_quotation (Non-Kontrak)
        if (Schema::hasTable('request_quotation') && !Schema::hasColumn('request_quotation', 'is_backdate')) {
            Schema::table('request_quotation', function (Blueprint $table) {
                $table->boolean('is_backdate')->default(0)->index()->after('flag_status');
            });
        }

        // 2. Tambah is_backdate di request_quotation_kontrak_H (Kontrak)
        if (Schema::hasTable('request_quotation_kontrak_H') && !Schema::hasColumn('request_quotation_kontrak_H', 'is_backdate')) {
            Schema::table('request_quotation_kontrak_H', function (Blueprint $table) {
                $table->boolean('is_backdate')->default(0)->index()->after('flag_status');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('request_quotation_kontrak_H') && Schema::hasColumn('request_quotation_kontrak_H', 'is_backdate')) {
            Schema::table('request_quotation_kontrak_H', function (Blueprint $table) {
                $table->dropColumn('is_backdate');
            });
        }

        if (Schema::hasTable('request_quotation') && Schema::hasColumn('request_quotation', 'is_backdate')) {
            Schema::table('request_quotation', function (Blueprint $table) {
                $table->dropColumn('is_backdate');
            });
        }
    }
}

