<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVendorReceiptAttachmentsToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('purchase_requests')) {
            return;
        }

        if (Schema::hasColumn('purchase_requests', 'vendor_receipt_attachments')) {
            return;
        }

        $afterVendorReceiptNote = Schema::hasColumn(
            'purchase_requests',
            'vendor_receipt_note'
        );

        Schema::table('purchase_requests', function (Blueprint $table) use ($afterVendorReceiptNote) {
            if ($afterVendorReceiptNote) {
                $table->text('vendor_receipt_attachments')->nullable()->after('vendor_receipt_note');
            } else {
                $table->text('vendor_receipt_attachments')->nullable();
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('purchase_requests')) {
            return;
        }

        if (!Schema::hasColumn('purchase_requests', 'vendor_receipt_attachments')) {
            return;
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('vendor_receipt_attachments');
        });
    }
}
