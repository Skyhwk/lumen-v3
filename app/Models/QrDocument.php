<?php

namespace App\Models;

use App\Models\Sector;

class QrDocument extends Sector
{
    protected $table = 'qr_documents';
    public $timestamps = false;
    protected $guarded = [];

    public function lhpManuals()
    {
        return $this->hasMany(LhpManual::class, 'file_qr', 'file')
            ->where('is_active', true)
            ->whereNull('deleted_at');
    }
}
