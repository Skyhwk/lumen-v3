<?php

namespace App\Models;

use App\Models\Sector;

class SamplerTrackingEvent extends Sector
{
    protected $table = 'sampler_tracking_events';
    protected $guarded = [];
    protected $appends = ['started_by_name'];

    public function session()
    {
        return $this->belongsTo(SamplerTrackingSession::class, 'sampler_tracking_session_id');
    }

    public function member()
    {
        return $this->belongsTo(SamplerTrackingMember::class, 'sampler_tracking_member_id');
    }

    public function triggeredBy()
    {
        return $this->belongsTo(SamplerTrackingMember::class, 'triggered_by_member_id');
    }

    public function getStartedByNameAttribute()
    {
        if ($this->event_type !== 'start_sampling') {
            return null;
        }

        if ($this->relationLoaded('triggeredBy') && $this->triggeredBy) {
            return $this->triggeredBy->sampler_name;
        }

        if ($this->relationLoaded('member') && $this->member) {
            return $this->member->sampler_name;
        }

        return null;
    }
}
