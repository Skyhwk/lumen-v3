<?php

namespace App\Models;

use App\Models\Sector;

class ClaimRewardHeader extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'claimed_h';
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'total_qty' => 'integer',
        'total_points' => 'integer',
        'is_active' => 'boolean',
        'processed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(ClaimRewardDetail::class, 'claim_id', 'id');
    }
}
