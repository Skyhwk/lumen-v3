<?php

namespace App\Models\Lims;

use App\Models\Sector;

class QrDocument extends Sector
{
    protected $connection = 'lims';

    protected $table = 'qr_documents';
    public $timestamps = false;
    protected $guarded = [];
}
