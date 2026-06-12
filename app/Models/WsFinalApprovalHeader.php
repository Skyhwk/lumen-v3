<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsFinalApprovalHeader extends Sector
{
    protected $table = 'ws_final_approval_header';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'parameter' => 'array',
        'regulasi' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(WsFinalApprovalDetail::class, 'ws_final_approval_header_id', 'id');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false);
    }

    public function scopeByNoOrder($query, string $noOrder)
    {
        return $query->where('no_order', $noOrder);
    }

    public function scopeByNoSampel($query, string $noSampel)
    {
        return $query->where('no_sampel', $noSampel);
    }

    public function markAsApproved(string $approvedBy): bool
    {
        return $this->update([
            'is_approved' => true,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);
    }
}
