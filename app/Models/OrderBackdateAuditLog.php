<?php

namespace App\Models;

class OrderBackdateAuditLog extends Sector
{
    protected $table = 'order_backdate_audit_logs';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function package()
    {
        return $this->belongsTo(OrderBackdatePackage::class, 'package_id', 'id');
    }
}
