<?php

namespace App\Models\customer;

use App\Models\Sector;

class CsTicketMessage extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'cs_ticket_messages';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_auto' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(CsTicket::class, 'ticket_id', 'id');
    }
}
