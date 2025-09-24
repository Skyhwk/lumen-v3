<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Notification extends Sector
{
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
