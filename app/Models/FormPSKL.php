<?php

namespace App\Models;

use App\Models\Sector;

class FormPSKL extends Sector
{
    protected $table = 'form_pskl';
    public $timestamps = false;
    protected $guarded = [];
    protected $with = ['order_header.orderDetail'];
    protected $casts = [
    'kategori_sk' => 'array',
];

    public function order_header()
    {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }
}
