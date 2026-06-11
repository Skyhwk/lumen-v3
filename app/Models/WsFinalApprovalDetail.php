<?php

namespace App\Models;

use App\Models\Sector;

class WsFinalApprovalDetail extends Sector
{
    protected $table = 'ws_final_approval_detail';

    public $timestamps = false;

    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(WsFinalApprovalHeader::class, 'ws_final_approval_header_id', 'id');
    }

    public function scopeByNoSampel($query, string $noSampel)
    {
        return $query->where('no_sampel', $noSampel);
    }

    public function scopeByParameter($query, string $parameter)
    {
        return $query->where('parameter', $parameter);
    }
}
