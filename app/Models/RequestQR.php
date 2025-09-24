<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DeviceIntilab;

class RequestQR extends Sector
{
    protected $table = 'request_qr';
    public $timestamps = false;

    protected $guarded = [];
}