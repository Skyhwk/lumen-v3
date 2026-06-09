<?php

namespace App\Models;

use App\Models\Sector;

class PurchaseRequest extends Sector
{
    protected $guarded = ['id'];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function employee()
    {
        return $this->belongsTo(MasterKaryawan::class, 'created_by', 'nama_lengkap');
    }

    public function receiptBatches()
    {
        return $this->hasMany(PurchaseReceiptBatch::class, 'purchase_request_id')->orderBy('batch_no');
    }
}
