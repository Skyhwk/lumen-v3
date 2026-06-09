<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMasterSuppliersAndPurchaseOrderDocumentsTables extends Migration
{
    public function up()
    {
        Schema::create('master_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('address')->nullable();
            $table->string('phone', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        Schema::create('purchase_order_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('supplier_name', 255);
            $table->text('supplier_address')->nullable();
            $table->string('item_name', 255);
            $table->decimal('quantity', 15, 2);
            $table->string('unit', 50)->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->decimal('ppn_percent', 5, 2)->default(11);
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('other_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->string('phone_fax', 100)->nullable();
            $table->string('pic', 255)->nullable();
            $table->string('payment_term', 255)->nullable();
            $table->string('item_status', 100)->nullable();
            $table->string('delivery_time', 100)->nullable();
            $table->string('delivery_type', 100)->nullable();
            $table->string('offer_ref', 255)->nullable();
            $table->text('shipping_address')->nullable();
            $table->date('po_date');
            $table->string('po_number', 100);
            $table->string('invoice_number', 100);
            $table->date('approval_date')->nullable();
            $table->string('approval_name', 255)->nullable();
            $table->string('qr_file', 255)->nullable();
            $table->string('created_by', 255)->nullable();
            $table->dateTime('created_at')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('purchase_order_documents');
        Schema::dropIfExists('master_suppliers');
    }
}
