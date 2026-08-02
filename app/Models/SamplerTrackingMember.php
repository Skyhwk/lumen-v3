<?php

namespace App\Models;

use App\Models\Sector;

class SamplerTrackingMember extends Sector
{
    protected $table = 'sampler_tracking_members';
    protected $guarded = [];

    public function session()
    {
        return $this->belongsTo(SamplerTrackingSession::class, 'sampler_tracking_session_id');
    }

    public function events()
    {
        return $this->hasMany(SamplerTrackingEvent::class, 'sampler_tracking_member_id');
    }
}
