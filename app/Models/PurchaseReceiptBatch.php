<?php

namespace App\Models;

class PurchaseReceiptBatch extends Sector
{
    protected $table = 'purchase_receipt_batches';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function purchaseOrderDocument()
    {
        return $this->belongsTo(PurchaseOrderDocument::class, 'purchase_order_document_id');
    }
}
