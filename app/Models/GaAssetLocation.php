<?php

namespace App\Models;

class GaAssetLocation extends Sector
{
    protected $table = 'ga_asset_locations';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'branch_id');
    }
}
