<?php

namespace App\Models;

use App\Models\Sector;

class SertifikatWebinarSurvei extends Sector
{
    protected $table = "sertifikat_webinar_survei";
    public $timestamps = false;
    
    protected $fillable = [
        'header_id',
        'name',
        'survei',
    ];
    protected $guarded = [];

    

}
