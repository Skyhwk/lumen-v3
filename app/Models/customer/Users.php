<?php

namespace App\Models\customer;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Users extends Sector
{
    protected $connection = "portal_customer";

    protected $table = "users";
    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * Get the customer account associated with the user.
     */
    public function custoken()
    {
        return $this->hasOne(\App\Models\CustomerToken::class, 'customer_account_id', 'id');
    }
 

}
