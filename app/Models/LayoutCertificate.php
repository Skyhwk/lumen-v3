<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class LayoutCertificate extends Sector {
    protected $table = "layout_certificates";
    public $timestamps = false;

    protected $guarded = [];
}