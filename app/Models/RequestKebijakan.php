<?php

namespace App\Models;

class RequestKebijakan extends Sector
{
    protected $table = 'request_kebijakan';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function requester()
    {
        return $this->belongsTo(MasterKaryawan::class, 'request_by', 'nama_lengkap');
    }
}
