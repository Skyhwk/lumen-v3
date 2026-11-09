<?php

namespace App\Models;

class OrderBackdatePackage extends Sector
{
    protected $table = 'order_backdate_packages';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const STEP_KEYS = [
        'quotation',
        'order',
        'schedule',
        'field_data',
        'sampling_doc',
        'lhp',
        'qr',
    ];

    public const VIEW_DISPLAY = 'display';
    public const VIEW_AUDIT = 'audit';

    public function steps()
    {
        return $this->hasMany(OrderBackdateStep::class, 'package_id', 'id');
    }

    public function auditLogs()
    {
        return $this->hasMany(OrderBackdateAuditLog::class, 'package_id', 'id')
            ->orderByDesc('id');
    }

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'id_order_header', 'id');
    }
}
