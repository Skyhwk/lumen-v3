<?php
namespace App\Models;
use App\Models\Sector;

class PapiRule extends Sector
{
    protected $table = "papi_rules";
    public $timestamps = false;


    public function role () {
        return $this->belongsTo(PapiRole::class,'role_id','id');
    }

}