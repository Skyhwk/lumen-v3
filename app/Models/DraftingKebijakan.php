<?php

namespace App\Models;

class DraftingKebijakan extends Sector
{
    protected $table = 'drafting_kebijakan';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function requestKebijakan()
    {
        return $this->belongsTo(RequestKebijakan::class, 'request_kebijakan_id');
    }
}
