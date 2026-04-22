<?php
namespace App\Models;

use App\Models\Sector;
use App\Models\PointClaimDetail;

class PointClaim extends Sector
{
    protected $table = 'point_claims';

    protected $fillable = [
        'customer_id',
        'total_points'
    ];

    public function details()
    {
        return $this->hasMany(PointClaimDetail::class, 'claim_id');
    }
}