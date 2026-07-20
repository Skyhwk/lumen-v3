<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class UploadQsd extends Sector
{
    protected $connection = 'lims';

    protected $table = 'upload_qsd';
    public $timestamps = false;

    protected $guarded = [];

}