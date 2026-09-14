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

    public function drafting()
    {
        return $this->hasOne(DraftingKebijakan::class, 'request_kebijakan_id');
    }

    public function verifiers()
    {
        return $this->hasMany(RequestKebijakanVerifier::class, 'request_kebijakan_id')
            ->where('is_active', true);
    }

    public function allVerifiers()
    {
        return $this->hasMany(RequestKebijakanVerifier::class, 'request_kebijakan_id');
    }

    public function kebijakanDokumen()
    {
        return $this->hasOne(KebijakanDokumen::class, 'request_kebijakan_id')
            ->where('is_active', true)
            ->whereIn('status', ['pending_director', 'returned_to_legal']);
    }

    public function activeKebijakanDokumen()
    {
        return $this->hasOne(KebijakanDokumen::class, 'request_kebijakan_id')
            ->where('is_active', true)
            ->where('status', 'active');
    }
}
