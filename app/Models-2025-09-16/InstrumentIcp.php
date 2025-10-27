<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use App\Models\DraftIcp;

class InstrumentIcp extends Sector{
    protected $table = 'instrument_icp';
    protected $guard = [];

    public $timestamps = false;

    public function colorimetri(){
        return $this->hasOne('App\Models\Colorimetri', 'no_sampel', 'no_sampel')->where('parameter', $this->parameter);
    }

    public function draft(){
        return $this->belongsTo(DraftIcp::class, 'no_sampel', 'no_sampel');
    }

    public function draft_air(){
        return $this->belongsTo(DraftAir::class, 'no_sampel', 'no_sampel');
    }

    public function emisi_header(){
        return $this->hasOne('App\Models\EmisiCerobongHeader', 'no_sampel', 'no_sampel');
    }
}