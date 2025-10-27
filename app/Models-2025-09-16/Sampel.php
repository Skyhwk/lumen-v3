<?php

namespace App\Models;

use App\Models\Sector;

class Sampel extends Sector
{
    // protected $connection = env("DB_INTILAB_" . date("Y"));
    protected $connection = 'lims';

    protected $table = 'sampel_sd';

    public $timestamps = false;
}
