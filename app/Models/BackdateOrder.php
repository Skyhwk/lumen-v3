<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackdateOrder extends Model
{
    protected $table = 'backdate_orders';
    protected $guarded = ['id'];

    public function details()
    {
        return $this->hasMany(BackdateOrderDetail::class, 'backdate_order_id', 'id');
    }
}

