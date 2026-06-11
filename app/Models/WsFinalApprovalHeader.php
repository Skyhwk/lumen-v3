<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WsFinalApprovalHeader extends Model
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
        return $this->hasMany(WsFinalApprovalDetail::class, 'ws_final_approval_header_id');
    }
}
