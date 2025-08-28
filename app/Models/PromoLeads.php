<?php

namespace App\Models;

use App\Models\Sector;

class PromoLeads extends Sector
{
    protected $table = 'promo_leads';
    protected $guarded = ['id'];
    public $timestamps = false;
}
