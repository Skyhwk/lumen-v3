<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueEmisiCerobong extends Sector
{
    protected $table = "ws_value_emisi_cerobong";
    public $timestamps = false;

    protected $guarded = [];

    public function parameter() {
        return $this->belongsTo('App\Models\Parameter', 'id_parameter', 'id');
    }
    public function subkontrak() {
        return $this->belongsTo('App\Models\Subkontrak', 'id_subkontrak', 'id');
    }

    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganEmisiCerobong', 'no_sampel', 'no_sampel');
    }
    public function emisi_cerobong_header() {
        return $this->belongsTo('App\Models\EmisiCerobongHeader', 'id_emisi_cerobong_header', 'id')->where('is_active', true);
    }

    public function emisi_isokinetik() {
        return $this->belongsTo('App\Models\IsokinetikHeader', 'id_isokinetik', 'id')->where('is_active', true);
    }

    public function getDataAnalyst()
    {
        if ($this->emisi_cerobong_header()->exists()) {
            return $this->emisi_cerobong_header;
        }
        if ($this->emisi_isokinetik()->exists()) {
            return $this->emisi_isokinetik;
        }
        if ($this->subkontrak()->exists()) {
            return $this->subkontrak;
        }
        return null;
    }
}