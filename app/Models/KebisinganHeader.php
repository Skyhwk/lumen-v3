<?php

namespace App\Models;

use App\Models\Concerns\SyncsWsFinalApproval;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KebisinganHeader extends Sector{
    use SyncsWsFinalApproval;

    protected $table = 'kebisingan_header';
    public $timestamps = false;

    protected $guarded = [];

    public function getDataPerShiftAttribute($value)
    {
        if (!$value) {
            return [];
        }

        $decoded = $value;
        for ($i = 0; $i < 3; $i++) {
            if (!is_string($decoded)) {
                break;
            }

            $candidate = json_decode($decoded, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $candidate = json_decode(str_replace('\\"', '"', $decoded), true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [];
            }

            $decoded = $candidate;
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function ws_udara() {
        return $this->belongsTo('App\Models\WsValueUdara', 'id', 'id_kebisingan_header');
    }

    public function data_lapangan() {
        return $this->belongsTo('App\Models\DataLapanganKebisingan', 'no_sampel', 'no_sampel');
    }

    public function data_lapangan_personal()
    {
        return $this->belongsTo('App\Models\DataLapanganKebisinganPersonal', 'no_sampel', 'no_sampel');
    }
    
    public function orderDetail()
    {
        return $this->belongsTo('App\Models\OrderDetail', 'no_sampel', 'no_sampel');
    }
}
