<?php

namespace App\Models;

use App\Models\Sector;

class SamplerTrackingEvent extends Sector
{
    protected $table = 'sampler_tracking_events';
    protected $guarded = [];

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
}
