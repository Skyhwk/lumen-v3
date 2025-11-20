<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HoldHp extends Sector
{
    protected $table = "hold_hp";
    public $timestamps = false;

    protected $guarded = [];
    public function orderHeader() {
        return $this->hasOne('App\Models\OrderHeader', 'no_order', 'no_order');
    }
}