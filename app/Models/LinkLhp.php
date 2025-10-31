<?php

namespace App\Models;

use App\Models\Sector;

class LinkLhp extends Sector
{
    protected $table = "link_lhp";
    protected $guarded = ['id'];

    public $timestamps = false;

    public function token() {
        return $this->belongsTo(GenerateLink::class, 'id_token', 'id');
    }

    public function order() {
        return $this->belongsTo(OrderHeader::class, 'no_order', 'no_order');
    }
}
