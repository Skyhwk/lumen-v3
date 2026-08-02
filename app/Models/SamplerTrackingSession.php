<?php

namespace App\Models;

use App\Models\Sector;

class SamplerTrackingSession extends Sector
{
    protected $table = 'sampler_tracking_sessions';
    protected $guarded = [];

    public function members()
    {
        return $this->hasMany(SamplerTrackingMember::class, 'sampler_tracking_session_id');
    }

    public function activeMembers()
    {
        return $this->hasMany(SamplerTrackingMember::class, 'sampler_tracking_session_id')->where('is_active', true);
    }

    public function events()
    {
        return $this->hasMany(SamplerTrackingEvent::class, 'sampler_tracking_session_id');
    }
}
