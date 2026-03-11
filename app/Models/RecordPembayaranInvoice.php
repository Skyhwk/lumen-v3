<?php

namespace App\Models;

use App\Models\Sector;

class RecordPembayaranInvoice extends Sector
{
    protected $table = 'record_pembayaran_invoice';
    protected $guarded = [];

    public $timestamps = false;

    public function sales_in_detail()
    {
        return $this->belongsTo(SalesInDetail::class, 'id_sales_in_detail', 'id');
    }
}