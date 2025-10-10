<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MetodeSampling extends Sector{
    protected $table = 'metode_sampling';
    protected $guarded = [];

    public $timestamps = false;

}