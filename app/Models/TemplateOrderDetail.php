<?php

namespace App\Models;

use App\Models\Sector;

class TemplateOrderDetail extends Sector
{
    protected $table = "template_order_detail";
    public $timestamps = false;

    public function orderHeader()
    {
        return $this->belongsTo(OrderHeader::class, 'id_order_header');
    }

    public function codingSampling()
    {
        return $this->hasOne(CodingSampling::class, 'no_sampel', 'no_sampel');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function TrackingSatu()
    {
        return $this->hasOne(Ftc::class, 'no_sample', 'no_sample');
    }
    public function TrackingDua()
    {
        return $this->hasOne(FtcT::class, 'no_sample', 'no_sample');
    }
}
