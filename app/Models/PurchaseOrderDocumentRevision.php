<?php

namespace App\Models;

class PurchaseOrderDocumentRevision extends Sector
{
    protected $table = 'purchase_order_document_revisions';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function poDocument()
    {
        return $this->belongsTo(PurchaseOrderDocument::class, 'purchase_order_document_id');
    }
}
