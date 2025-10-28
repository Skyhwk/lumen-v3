<?php
namespace App\Models;

use App\Models\Sector;

class CustomerToken extends Sector
{
    protected $connection = "apps";
    protected $table = 'customer_token';

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(CustomerAccount::class, 'customer_account_id', 'id');
    }
}
