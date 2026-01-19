<?php

namespace App\Models;

use App\Models\Sector;

class SertifikatWebinarDetail extends Sector
{
    protected $table = "sertifikat_webinar_detail";
    public $timestamps = false;
    
    protected $fillable = [
        'header_id',
        'name',
        'email',
        'filename'
    ];
    protected $guarded = [];

    

}
