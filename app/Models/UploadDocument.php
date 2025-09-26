<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class UploadDocument extends Sector{
    protected $table = 'upload_documents';
    public $timestamps = false;

    protected $guarded = [];

}