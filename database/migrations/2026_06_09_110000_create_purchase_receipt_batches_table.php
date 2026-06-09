<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePurchaseReceiptBatchesTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->decimal('receipt_target_qty', 15, 2)->nullable()->after('handover_number');
            $table->decimal('vendor_received_total', 15, 2)->default(0)->after('receipt_target_qty');
            $table->decimal('user_handed_total', 15, 2)->default(0)->after('vendor_received_total');
            $table->decimal('user_confirmed_total', 15, 2)->default(0)->after('user_handed_total');
        });

        Schema::create('purchase_receipt_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->unsignedInteger('batch_no')->default(1);
            $table->decimal('vendor_receipt_qty', 15, 2);
            $table->string('vendor_delivery_note', 255)->nullable();
            $table->text('vendor_receipt_note')->nullable();
            $table->text('vendor_receipt_attachments')->nullable();
            $table->string('vendor_receipt_by', 255)->nullable();
            $table->dateTime('vendor_receipt_at')->nullable();
            $table->string('handover_number', 50)->nullable();
            $table->decimal('user_handover_qty', 15, 2)->nullable();
            $table->text('user_receipt_note')->nullable();
            $table->string('user_receipt_by', 255)->nullable();
            $table->dateTime('user_receipt_at')->nullable();
            $table->string('completed_by', 255)->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index(['purchase_request_id', 'batch_no']);
        });

        $legacyRows = DB::table('purchase_requests')
            ->whereNotNull('vendor_receipt_qty')
            ->where('vendor_receipt_qty', '>', 0)
            ->get();

        foreach ($legacyRows as $row) {
            $targetQty = (float) $row->vendor_receipt_qty;
            if (!empty($row->receipt_target_qty)) {
                $targetQty = max($targetQty, (float) $row->receipt_target_qty);
            }

            $batchId = DB::table('purchase_receipt_batches')->insertGetId([
                'purchase_request_id' => $row->id,
                'batch_no' => 1,
                'vendor_receipt_qty' => $row->vendor_receipt_qty,
                'vendor_delivery_note' => $row->vendor_delivery_note,
                'vendor_receipt_note' => $row->vendor_receipt_note,
                'vendor_receipt_attachments' => $row->vendor_receipt_attachments,
                'vendor_receipt_by' => $row->vendor_receipt_by,
                'vendor_receipt_at' => $row->vendor_receipt_at,
                'handover_number' => $row->handover_number,
                'user_handover_qty' => $row->handover_number ? $row->vendor_receipt_qty : null,
                'user_receipt_note' => $row->user_receipt_note,
                'user_receipt_by' => $row->user_receipt_by,
                'user_receipt_at' => $row->user_receipt_at,
                'completed_by' => $row->completed_by,
                'completed_at' => $row->completed_at,
                'created_at' => $row->vendor_receipt_at ?: date('Y-m-d H:i:s'),
            ]);

            $handedQty = $row->handover_number ? (float) $row->vendor_receipt_qty : 0;
            $confirmedQty = $row->completed_at ? (float) $row->vendor_receipt_qty : 0;

            DB::table('purchase_requests')->where('id', $row->id)->update([
                'receipt_target_qty' => $targetQty > 0 ? $targetQty : $row->vendor_receipt_qty,
                'vendor_received_total' => $row->vendor_receipt_qty,
                'user_handed_total' => $handedQty,
                'user_confirmed_total' => $confirmedQty,
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('purchase_receipt_batches');

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_target_qty',
                'vendor_received_total',
                'user_handed_total',
                'user_confirmed_total',
            ]);
        });
    }
}
