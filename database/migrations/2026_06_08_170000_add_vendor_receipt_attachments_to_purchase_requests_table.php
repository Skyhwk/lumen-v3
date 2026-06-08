<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorReceiptAttachmentsToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->text('vendor_receipt_attachments')->nullable()->after('vendor_receipt_note');
        });
    }

    public function down()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('vendor_receipt_attachments');
        });
    }
}
