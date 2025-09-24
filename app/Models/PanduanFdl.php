<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PanduanFdl extends Sector{
    protected $table = 'panduan_fdl';
    protected $guard = [];

    public $timestamps = false;
}