<?php

namespace App\Models;

use App\Models\Sector;

class ChangeRequest extends Sector
{
    protected $table = 'change_requests';
    
    protected $guarded = ['id'];

    // Cast attributes
    protected $casts = [
        'dampak' => 'array',
        'is_active' => 'boolean',
        'tanggal_permintaan' => 'date:Y-m-d',
        'tanggal_development' => 'date:Y-m-d',
        'tanggal_testing' => 'date:Y-m-d',
        'tanggal_release' => 'date:Y-m-d',
        'disetujui_user_at' => 'datetime:Y-m-d H:i:s',
        'disetujui_it_at' => 'datetime:Y-m-d H:i:s',
    ];
}
