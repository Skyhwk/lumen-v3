<?php

namespace App\Models\customer;

use App\Models\Sector;

class CsTicketMessage extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'cs_ticket_messages';
    protected $guarded = [];
    public $timestamps = false;

    public function ticket()
    {
        return $this->belongsTo(CsTicket::class, 'ticket_id', 'id');
    }
}
