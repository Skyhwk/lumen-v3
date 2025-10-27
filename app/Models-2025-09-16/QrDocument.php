<?php

namespace App\Models;

use App\Models\Sector;

class QrDocument extends Sector
{
    protected $table = 'qr_documents';
    protected $guarded = [];
    public $timestamps = false;
}
