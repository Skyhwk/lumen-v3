<?php

namespace App\Models;

class KebijakanDokumen extends Sector
{
    protected $table = 'kebijakan_dokumen';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function requestKebijakan()
    {
        return $this->belongsTo(RequestKebijakan::class, 'request_kebijakan_id');
    }

    public function drafting()
    {
        return $this->belongsTo(DraftingKebijakan::class, 'drafting_kebijakan_id');
    }

    public function parentDokumen()
    {
        return $this->belongsTo(self::class, 'parent_dokumen_id');
    }

    public function supersededBy()
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }
}
