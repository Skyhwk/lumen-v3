<?php

namespace App\Models;

use App\Models\Sector;

class CustomerAccount extends Sector
{
    protected $table = 'customer_account';
    protected $guarded = ['id'];

    public function tokens()
    {
        return $this->hasMany(CustomerToken::class);
    }
}
