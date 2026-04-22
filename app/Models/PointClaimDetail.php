<?php
namespace App\Models;

use App\Models\Sector;
use App\Models\PointEarning;
use App\Models\PointClaim;

class PointClaimDetail extends Sector
{
    protected $table = 'point_claim_details';

    protected $fillable = [
        'claim_id',
        'earning_id',
        'points_used'
    ];

    public function earning()
    {
        return $this->belongsTo(PointEarning::class, 'earning_id');
    }

    public function claim()
    {
        return $this->belongsTo(PointClaim::class, 'claim_id');
    }
}