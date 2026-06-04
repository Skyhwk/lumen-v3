<?php

namespace App\Models\customer;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Notifications extends Sector
{
    protected $connection = 'portal_customer';
    protected $table = 'notification';
    public $timestamps = false;
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'url',
        'data',
        'is_read',
        'created_at',
        'is_active',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'is_active' => 'boolean',
    ];

}
