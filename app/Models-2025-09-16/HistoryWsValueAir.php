<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HistoryWsValueAir extends Sector
{
    protected $table = "history_ws_value_air";
    public $timestamps = false;

    protected $guarded = [];

    public function titrimetri()
    {
        return $this->belongsTo('App\Models\Titrimetri', 'id_titrimetri', 'id')->where('is_active', true);
    }
    public function dataLapanganAir()
    {
        return $this->belongsTo('App\Models\DataLapanganAir', 'no_sampel', 'no_sampel');
    }
    public function gravimetri()
    {
        return $this->belongsTo('App\Models\Gravimetri', 'id_gravimetri', 'id')->where('is_active', true);
    }
    public function colorimetri()
    {
        return $this->belongsTo('App\Models\Colorimetri', 'id_colorimetri', 'id')->where('is_active', true);
    }
    public function subkontrak()
    {
        return $this->belongsTo('App\Models\Subkontrak', 'id_subkontrak', 'id')->where('is_active', true);
    }
}