<?php

namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HoldHp extends Sector
{
    protected $connection = 'lims';

    protected $table = "hold_hp";
    public $timestamps = false;

    protected $guarded = [];
    public function orderHeader() {
        return $this->hasOne('App\Models\Lims\OrderHeader', 'no_order', 'no_order');
    }
}