<?php

namespace App\Models;

class CiscoPhoneDirectoryEntry extends Sector
{
    protected $table = 'cisco_phone_directory_entries';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}