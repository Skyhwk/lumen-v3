<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketProgrammingConversation extends Model
{
    protected $table = 'ticket_programming_conversations';
    protected $guarded = [];
    public $timestamps = false;

    public function ticket()
    {
        return $this->belongsTo(TicketProgramming::class, 'ticket_programming_id', 'id');
    }
}
