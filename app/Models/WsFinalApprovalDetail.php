<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WsFinalApprovalDetail extends Model
{
    protected $table = 'ws_final_approval_detail';
    public $timestamps = false;

    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(WsFinalApprovalHeader::class, 'ws_final_approval_header_id');
    }
}
