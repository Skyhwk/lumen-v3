<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddGoodsReceiptFlowToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Waiting Vendor Receipt',
            'Waiting User Receipt',
            'Distributed'
        ) NULL");

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('po_approved_by', 255)->nullable()->after('po_created_at');
            $table->dateTime('po_approved_at')->nullable()->after('po_approved_by');
            $table->dateTime('vendor_receipt_at')->nullable()->after('po_approved_at');
            $table->string('vendor_receipt_by', 255)->nullable()->after('vendor_receipt_at');
            $table->string('vendor_delivery_note', 255)->nullable()->after('vendor_receipt_by');
            $table->decimal('vendor_receipt_qty', 15, 2)->nullable()->after('vendor_delivery_note');
            $table->text('vendor_receipt_note')->nullable()->after('vendor_receipt_qty');
            $table->dateTime('user_receipt_at')->nullable()->after('vendor_receipt_note');
            $table->string('user_receipt_by', 255)->nullable()->after('user_receipt_at');
            $table->text('user_receipt_note')->nullable()->after('user_receipt_by');
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'po_approved_by',
                'po_approved_at',
                'vendor_receipt_at',
                'vendor_receipt_by',
                'vendor_delivery_note',
                'vendor_receipt_qty',
                'vendor_receipt_note',
                'user_receipt_at',
                'user_receipt_by',
                'user_receipt_note',
            ]);
        });

        DB::statement("UPDATE purchase_requests SET finance_status = 'On Process' WHERE finance_status IN ('Waiting Vendor Receipt', 'Waiting User Receipt')");

        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Distributed'
        ) NULL");
    }
}
