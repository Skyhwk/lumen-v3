<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class WsFinalApprovalDetail extends Sector
{
    protected $table = 'ws_final_approval_detail';

    public $timestamps = false;

    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(WsFinalApprovalHeader::class, 'ws_final_approval_header_id', 'id');
    }

    public function scopeByNoSampel($query, string $noSampel)
    {
        return $query->where('no_sampel', $noSampel);
    }

    public function scopeByParameter($query, string $parameter)
    {
        return $query->where('parameter', $parameter);
    }

    public function wsValueAir()
    {
        return $this->hasMany(WsValueAir::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function wsValueUdara()
    {
        return $this->hasMany(WsValueUdara::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function wsValueEmisiCerobong()
    {
        return $this->hasMany(WsValueEmisiCerobong::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function wsValueSwab()
    {
        return $this->hasMany(WsValueSwab::class, 'no_sampel', 'no_sampel')->where('is_active', true);
    }

    public function scopeWithDataAnalisa($query, ?OrderDetail $orderDetail = null)
    {
        if ($orderDetail) {
            if ($orderDetail->isAir()) {
                return $query->with([
                    'wsValueAir.colorimetri',
                    'wsValueAir.titrimetri',
                    'wsValueAir.gravimetri',
                    'wsValueAir.subkontrak'
                ]);
            } elseif ($orderDetail->isUdara()) {
                return $query->with([
                    'wsValueUdara.lingkungan',
                    'wsValueUdara.microbiologi',
                    'wsValueUdara.medanLm',
                    'wsValueUdara.sinaruv',
                    'wsValueUdara.iklim',
                    'wsValueUdara.getaran',
                    'wsValueUdara.kebisingan',
                    'wsValueUdara.direct_lain',
                    'wsValueUdara.partikulat',
                    'wsValueUdara.pencahayaan',
                    'wsValueUdara.swab',
                    'wsValueUdara.subkontrak',
                    'wsValueUdara.dustfall',
                    'wsValueUdara.debuPersonal'
                ]);
            } elseif ($orderDetail->isEmisi()) {
                return $query->with([
                    'wsValueEmisiCerobong.subkontrak',
                    'wsValueEmisiCerobong.emisi_cerobong_header',
                    'wsValueEmisiCerobong.emisi_isokinetik',
                ]);
            } elseif ($orderDetail->isSwab()) {
                return $query->with([
                    'wsValueSwab.swab',
                ]);
            }
        } 

        return $query;
    }
}
