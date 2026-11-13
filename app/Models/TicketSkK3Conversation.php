<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSkK3Conversation extends Model
{
    protected $table = 'ticket_sk_k3_conversations';
    protected $guarded = [];
    public $timestamps = false;

    public function ticket()
    {
        return $this->belongsTo(TicketSkK3::class, 'ticket_sk_k3_id', 'id');
    }
}
