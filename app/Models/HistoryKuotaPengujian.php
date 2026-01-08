<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class HistoryKuotaPengujian extends Sector {
    protected $table = "history_kuota_pengujian";
    public $timestamps = false;

    protected $guarded = [];

    public function kuota_pengujian() {
        return $this->belongsTo(KuotaPengujian::class, 'id_kuota', 'id');
    }
}