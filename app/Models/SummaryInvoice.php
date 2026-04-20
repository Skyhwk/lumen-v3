<?php

namespace App\Models;

use App\Models\Sector;

class SummaryInvoice extends Sector
{
    protected $table = 'summary_invoice';
    protected $guarded = ['id'];
    public $timestamps = false;
}
