<?php

namespace App\Models;

use App\Models\Sector;

class AccountSIP extends Sector
{
    protected $table = 'account_sip';
    // protected $connection = 'intilab_2024';

    public function logSIP()
    {
        return $this->hasMany(LogSIP::class, 'from', 'username');
    }

    public function latestLogSIP()
    {
        return $this->hasOne(LogSIP::class, 'from', 'username')->latest('created_at');
    }
}
