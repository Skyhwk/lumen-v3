<?php
namespace App\Models;
use App\Models\Sector;

class PapiRole extends Sector
{
    protected $table = "papi_roles";
    public $timestamps = false;


    public function aspect()
    {
        return $this->belongsTo(PapiAspect::class, 'aspect_id', 'id');
    }

}