<?php

namespace App\Models;

use App\Models\Sector;

class EmailLhp extends Sector
{
    protected $table = 'email_lhp';
    public $timestamps = false;
    protected $guarded = [];

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'id_pelanggan', 'id_pelanggan');
    }
}
