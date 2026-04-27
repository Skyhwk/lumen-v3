<?php

namespace App\Models;

use App\Models\Sector;
use App\Models\PointClaimDetail;

class PointEarning extends Sector
{
    protected $table = 'point_earnings';

    protected $fillable = [
        'customer_id',
        'source_type',
        'source_id',
        'points',
        'claimed_points',
        'expired_points',
        'earned_at',
        'claim_expired_at',
        'tier_expired_at'
    ];

    protected $dates = [
        'earned_at',
        'claim_expired_at',
        'tier_expired_at'
    ];

    public function claimDetails()
    {
        return $this->hasMany(PointClaimDetail::class, 'earning_id');
    }
}