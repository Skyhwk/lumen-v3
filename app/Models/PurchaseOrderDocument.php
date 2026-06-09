<?php

namespace App\Models;

class PurchaseOrderDocument extends Sector
{
    protected $table = 'purchase_order_documents';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function supplier()
    {
        return $this->belongsTo(MasterSupplier::class, 'supplier_id');
    }
}
