<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsProcessedToQuotationAutoLinkOpensTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('quotation_auto_link_opens')
            || Schema::hasColumn('quotation_auto_link_opens', 'is_processed')) {
            return;
        }

        Schema::table('quotation_auto_link_opens', function (Blueprint $table) {
            $column = $table->boolean('is_processed')->default(false);
            if (Schema::hasColumn('quotation_auto_link_opens', 'open_count')) {
                $column->after('open_count');
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('quotation_auto_link_opens')
            || !Schema::hasColumn('quotation_auto_link_opens', 'is_processed')) {
            return;
        }

        Schema::table('quotation_auto_link_opens', function (Blueprint $table) {
            $table->dropColumn('is_processed');
        });
    }
}
