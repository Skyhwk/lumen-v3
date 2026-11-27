<?php

namespace App\Models;

class BlokLhp extends Sector
{
    protected $table = 'blok_lhp';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
