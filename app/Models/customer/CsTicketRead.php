<?php

namespace App\Models\customer;

use App\Models\Sector;

class CsTicketRead extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'cs_ticket_reads';
    protected $guarded = [];
    public $timestamps = false;
}
