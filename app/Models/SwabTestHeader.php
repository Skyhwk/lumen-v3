<?php

namespace App\Models;

use App\Models\Concerns\SyncsWsFinalApproval;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class SwabTestHeader extends Sector{
    use SyncsWsFinalApproval;

    protected $table = 'swabtest_header';
    public $timestamps = false;

    protected $guarded = [];

    public function TrackingSatu()
    {
        return $this->hasOne('App\Models\Ftc'::class, 'no_sample', 'no_sampel');
    }

    public function ws_value()
    {
        return $this->hasOne(WsValueUdara::class, 'id_swabtest_header', 'id');
    }
}
