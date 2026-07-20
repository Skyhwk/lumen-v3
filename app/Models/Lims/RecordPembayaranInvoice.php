<?php

namespace App\Models\Lims;

use App\Models\Sector;

class RecordPembayaranInvoice extends Sector
{
    protected $connection = 'lims';

    protected $table = 'record_pembayaran_invoice';
    protected $guarded = [];

    public $timestamps = false;

    public function sales_in_detail()
    {
        return $this->belongsTo(SalesInDetail::class, 'id_sales_in_detail', 'id');
    }
}