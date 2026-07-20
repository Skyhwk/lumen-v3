<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PraNoSample extends Sector
{
    protected $connection = 'lims';
    protected $table = "pra_no_sample";
    public $timestamps = false;

    protected $guarded = [];
}
