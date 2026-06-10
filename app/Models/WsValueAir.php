<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsValueAir extends Sector
{
    protected $table = "ws_value_air";
    public $timestamps = false;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (WsValueAir $model) {
            $resolved = $model->resolveParameterFromChild();
            if ($resolved !== null) {
                $model->parameter = $resolved;
            }
        });
    }

    /**
     * Ambil parameter dari child (colorimetri, titrimetri, gravimetri, subkontrak).
     */
    public function resolveParameterFromChild(): ?string
    {
        if ($this->id_colorimetri) {
            $parameter = Colorimetri::where('id', $this->id_colorimetri)->value('parameter');
            if ($parameter !== null) {
                return $parameter;
            }
        }

        if ($this->id_titrimetri) {
            $parameter = Titrimetri::where('id', $this->id_titrimetri)->value('parameter');
            if ($parameter !== null) {
                return $parameter;
            }
        }

        if ($this->id_gravimetri) {
            $parameter = Gravimetri::where('id', $this->id_gravimetri)->value('parameter');
            if ($parameter !== null) {
                return $parameter;
            }
        }

        if ($this->id_subkontrak) {
            $parameter = Subkontrak::where('id', $this->id_subkontrak)->value('parameter');
            if ($parameter !== null) {
                return $parameter;
            }
        }

        return null;
    }

    public function titrimetri() {
        return $this->belongsTo('App\Models\Titrimetri', 'id_titrimetri', 'id')->where('is_active', true);
    }
    public function dataLapanganAir() {
        return $this->belongsTo('App\Models\DataLapanganAir', 'no_sampel', 'no_sampel');
    }
    public function gravimetri() {
        return $this->belongsTo('App\Models\Gravimetri', 'id_gravimetri', 'id')->where('is_active', true);
    }
    public function colorimetri() {
        return $this->belongsTo('App\Models\Colorimetri', 'id_colorimetri', 'id')->where('is_active', true);
    }
    public function subkontrak() {
        return $this->belongsTo('App\Models\Subkontrak', 'id_subkontrak', 'id')->where('is_active', true);
    }

    // Tracking
    public function getDataAnalyst()
    {
        if ($this->titrimetri()->exists()) {
            return $this->titrimetri;
        }
        if ($this->gravimetri()->exists()) {
            return $this->gravimetri;
        }
        if ($this->colorimetri()->exists()) {
            return $this->colorimetri;
        }
        if ($this->subkontrak()->exists()) {
            return $this->subkontrak;
        }
        return null;
    }
}
