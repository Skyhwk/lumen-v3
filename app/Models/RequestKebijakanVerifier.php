<?php

namespace App\Models;

class RequestKebijakanVerifier extends Sector
{
    protected $table = 'request_kebijakan_verifiers';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function requestKebijakan()
    {
        return $this->belongsTo(RequestKebijakan::class, 'request_kebijakan_id');
    }

    public function verifier()
    {
        return $this->belongsTo(MasterKaryawan::class, 'verifier_karyawan_id');
    }
}
