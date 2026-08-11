<?php

namespace App\Models;

class CiscoPhoneConfig extends Sector
{
    protected $table = 'cisco_phone_configs';

    protected $guarded = ['id'];

    protected $casts = [
        'config_json' => 'array',
        'is_active' => 'boolean',
        'last_generated_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(MasterKaryawan::class, 'created_by');
    }
}
