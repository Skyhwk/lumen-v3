<?php

namespace App\Models;

use App\Models\Sector;

class LogSip extends Sector
{
    protected $table = 'log_sip'; 
    protected $connection = 'intilab_2024';
}
