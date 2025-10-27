<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class UploadQsd extends Sector
{
    protected $table = 'upload_qsd';
    public $timestamps = false;

    protected $guarded = [];

}