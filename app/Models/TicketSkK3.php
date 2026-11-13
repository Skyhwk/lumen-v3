<?php

namespace App\Models;

class TicketSkK3 extends Sector
{
    protected $table = 'ticket_sk_k3';
    protected $guarded = [];
    public $timestamps = false;

    public function conversations()
    {
        return $this->hasMany(TicketSkK3Conversation::class, 'ticket_sk_k3_id', 'id');
    }

    public function samplingPlan()
    {
        return $this->belongsTo(SamplingPlan::class, 'sampling_plan_id', 'id');
    }
}
