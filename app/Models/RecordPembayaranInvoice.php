<?php

namespace App\Models;

use App\Models\Sector;

class RecordPembayaranInvoice extends Sector
{
    protected $table = 'record_pembayaran_invoice';
    protected $guarded = [];

    public $timestamps = false;
}