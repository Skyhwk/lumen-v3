<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackdateOrderDetail extends Model
{
    protected $table = 'backdate_order_details';
    protected $guarded = ['id'];

    public function backdateOrder()
    {
        return $this->belongsTo(BackdateOrder::class, 'backdate_order_id', 'id');
    }
}

