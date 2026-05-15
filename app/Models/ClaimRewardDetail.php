<?php

namespace App\Models;

use App\Models\Sector;

class ClaimRewardDetail extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'claimed_d';
    protected $guarded = [];

    protected $casts = [
        'reward_snapshot' => 'array',
        'qty' => 'integer',
        'unit_points' => 'integer',
        'total_points' => 'integer',
        'is_active' => 'boolean',
    ];

    public function claim()
    {
        return $this->belongsTo(ClaimRewardHeader::class, 'claim_id', 'id');
    }

    public function reward()
    {
        return $this->belongsTo(KatalogReward::class, 'reward_id', 'id');
    }
}
