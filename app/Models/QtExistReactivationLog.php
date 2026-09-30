<?php

namespace App\Models;

class QtExistReactivationLog extends Sector
{
    protected $table = 'qt_exist_reactivation_logs';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
