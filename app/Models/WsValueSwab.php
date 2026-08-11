<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueSwab extends Sector
{
    protected $table = "ws_value_swab";
    public $timestamps = false;

     public function swab() {
        return $this->belongsTo('App\Models\SwabTestHeader', 'id_swab_header', 'id')->where('is_active', true);
    }

    public function getDataAnalyst()
    {
        return $this->swab;
    }

    public function getHasilAnalyst()
    {
        return $this->swab;
    }

    public function getParameterAttribute()
    {
        return optional($this->getDataAnalyst())->parameter;
    }

    public function getHasilParameterAttribute()
    {
        return optional($this->getHasilAnalyst())->parameter;
    }

    public function getHasilParameterAttribute()
    {
        return optional($this->getHasilAnalyst())->parameter;
    }

    protected $guarded = [];
}