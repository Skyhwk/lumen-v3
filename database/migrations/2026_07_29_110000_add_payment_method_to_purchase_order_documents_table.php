<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPaymentMethodToPurchaseOrderDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_documents', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('payment_term');
            }
        });

        DB::table('purchase_order_documents')
            ->whereNull('payment_method')
            ->update(['payment_method' => 'transfer']);
    }

    public function down()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_order_documents', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
}
