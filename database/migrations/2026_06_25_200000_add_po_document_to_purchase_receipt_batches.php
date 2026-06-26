<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddPoDocumentToPurchaseReceiptBatches extends Migration
{
    public function up()
    {
        Schema::table('purchase_receipt_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_receipt_batches', 'purchase_order_document_id')) {
                $table->unsignedBigInteger('purchase_order_document_id')->nullable()->after('purchase_request_id');
                $table->index(['purchase_order_document_id'], 'prb_po_document_idx');
            }
        });

        $batchRows = DB::table('purchase_receipt_batches')
            ->whereNull('purchase_order_document_id')
            ->orderBy('purchase_request_id')
            ->orderBy('batch_no')
            ->get();

        foreach ($batchRows->groupBy('purchase_request_id') as $purchaseRequestId => $batches) {
            $activePos = DB::table('purchase_order_documents')
                ->where('purchase_request_id', $purchaseRequestId)
                ->where(function ($query) {
                    $query->where('is_voided', false)->orWhereNull('is_voided');
                })
                ->where('po_status', 'active')
                ->orderBy('processed_at')
                ->orderBy('id')
                ->get(['id', 'quantity']);

            if ($activePos->isEmpty()) {
                $fallbackPo = DB::table('purchase_order_documents')
                    ->where('purchase_request_id', $purchaseRequestId)
                    ->where(function ($query) {
                        $query->where('is_voided', false)->orWhereNull('is_voided');
                    })
                    ->whereIn('po_status', ['draft', 'active'])
                    ->orderByDesc('id')
                    ->value('id');

                if ($fallbackPo) {
                    DB::table('purchase_receipt_batches')
                        ->whereIn('id', $batches->pluck('id'))
                        ->update(['purchase_order_document_id' => $fallbackPo]);
                }

                continue;
            }

            if ($activePos->count() === 1) {
                DB::table('purchase_receipt_batches')
                    ->whereIn('id', $batches->pluck('id'))
                    ->update(['purchase_order_document_id' => $activePos->first()->id]);

                continue;
            }

            $poIndex = 0;
            $poRemaining = (float) $activePos[$poIndex]->quantity;

            foreach ($batches as $batch) {
                $batchQty = (float) $batch->vendor_receipt_qty;

                while ($poIndex < $activePos->count() && $poRemaining <= 0) {
                    $poIndex++;
                    $poRemaining = $poIndex < $activePos->count()
                        ? (float) $activePos[$poIndex]->quantity
                        : 0;
                }

                $assignedPoId = $poIndex < $activePos->count()
                    ? $activePos[$poIndex]->id
                    : $activePos->last()->id;

                DB::table('purchase_receipt_batches')
                    ->where('id', $batch->id)
                    ->update(['purchase_order_document_id' => $assignedPoId]);

                $poRemaining -= $batchQty;
            }
        }
    }

    public function down()
    {
        Schema::table('purchase_receipt_batches', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_receipt_batches', 'purchase_order_document_id')) {
                $table->dropIndex('prb_po_document_idx');
                $table->dropColumn('purchase_order_document_id');
            }
        });
    }
}
