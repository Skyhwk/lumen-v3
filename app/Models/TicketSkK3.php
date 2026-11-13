<?php

namespace App\Models;

class TicketSkK3 extends Sector
{
    protected $table = 'ticket_sk_k3';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
