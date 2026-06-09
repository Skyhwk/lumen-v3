<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVoidFieldsToPurchaseOrderDocumentsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->boolean('is_voided')->default(false)->after('created_at');
            $table->string('voided_by', 255)->nullable()->after('is_voided');
            $table->dateTime('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
            $table->string('void_from_finance_status', 50)->nullable()->after('void_reason');
        });
    }

    public function down()
    {
        Schema::table('purchase_order_documents', function (Blueprint $table) {
            $table->dropColumn([
                'is_voided',
                'voided_by',
                'voided_at',
                'void_reason',
                'void_from_finance_status',
            ]);
        });
    }
}
