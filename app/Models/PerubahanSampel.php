<?php

namespace App\Models;

use App\Models\Sector;

class PerubahanSampel extends Sector
{
    protected $table = 'perubahan_no_sampel';
    public $timestamps = false;
    protected $guarded = [];

    public function orderH()
    {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }

    public function orderD()
    {
        return $this->hasMany(OrderDetail::class, 'no_order', 'no_order');
    }
}
