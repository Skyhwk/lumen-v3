<?php

namespace App\Models;

use App\Models\Sector;

class PurchaseRequestItem extends Sector
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
