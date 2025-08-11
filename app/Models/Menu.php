<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Menu extends Sector
{
    protected $table = 'menu';

    protected $fillable = [
        'icon', 'menu', 'submenu', 'is_active'
    ];

    protected $casts = [
        'submenu' => 'json',
    ];

    public $timestamps = false;  // Set to true if you have created_at and updated_at columns
}
