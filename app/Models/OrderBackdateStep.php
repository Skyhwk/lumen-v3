<?php

namespace App\Models;

class OrderBackdateStep extends Sector
{
    protected $table = 'order_backdate_steps';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'snapshot_original' => 'array',
        'applied_values' => 'array',
        'last_payload' => 'array',
    ];

    public function package()
    {
        return $this->belongsTo(OrderBackdatePackage::class, 'package_id', 'id');
    }
}
