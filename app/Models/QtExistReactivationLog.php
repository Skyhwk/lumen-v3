<?php

namespace App\Models;

class QtExistReactivationLog extends Sector
{
    protected $table = 'qt_exist_reactivation_logs';

    protected $guarded = [];

    public $timestamps = false;

    const TYPE_NEW = 'new';
}
