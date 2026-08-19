<?php

namespace App\Models\customer;

use App\Models\Sector;

class CsTicket extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'cs_tickets';
    protected $guarded = [];
    public $timestamps = false;

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function messages()
    {
        return $this->hasMany(CsTicketMessage::class, 'ticket_id', 'id');
    }

    public function reads()
    {
        return $this->hasMany(CsTicketRead::class, 'ticket_id', 'id');
    }
}
