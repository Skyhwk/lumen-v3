<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RejectedFdl extends Model
{
    protected $table = 'rejected_fdls';

    protected $guarded = [];

    public function order_detail()
    {
        return $this->belongsTo(OrderDetail::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }
}
