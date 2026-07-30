<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPurchaseProcessTransactionFields extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transaction_no')) {
                $table->string('purchase_transaction_no', 100)->nullable()->after('processed_at');
            }
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transaction_date')) {
                $table->date('purchase_transaction_date')->nullable()->after('purchase_transaction_no');
            }
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transaction_note')) {
                $table->text('purchase_transaction_note')->nullable()->after('purchase_transaction_date');
            }
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transaction_proof')) {
                $table->text('purchase_transaction_proof')->nullable()->after('purchase_transaction_note');
            }
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transacted_by')) {
                $table->string('purchase_transacted_by', 255)->nullable()->after('purchase_transaction_proof');
            }
            if (!Schema::hasColumn('purchase_order_documents', 'purchase_transacted_at')) {
                $table->dateTime('purchase_transacted_at')->nullable()->after('purchase_transacted_by');
            }
        });
    }

    public function down()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $columns = [
                'purchase_transaction_no',
                'purchase_transaction_date',
                'purchase_transaction_note',
                'purchase_transaction_proof',
                'purchase_transacted_by',
                'purchase_transacted_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('purchase_order_documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
