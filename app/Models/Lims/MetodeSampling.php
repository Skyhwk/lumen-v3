<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MetodeSampling extends Sector{
    protected $connection = 'lims';

    protected $table = 'metode_sampling';
    protected $guarded = [];

    public $timestamps = false;

}