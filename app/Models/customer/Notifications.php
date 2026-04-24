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
        'karyawan_id',
        'status',
        'message',
        'url',
        'timestamps',
    ];

}
