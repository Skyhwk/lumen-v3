<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddHandoverDocumentToPurchaseRequestsTable extends Migration
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
            'Distributing',
            'Distributed'
        ) NULL");

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('handover_number', 50)->nullable()->after('vendor_receipt_attachments');
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('handover_number');
        });

        DB::statement("UPDATE purchase_requests SET finance_status = 'Waiting User Receipt' WHERE finance_status = 'Distributing'");

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
    }
}
