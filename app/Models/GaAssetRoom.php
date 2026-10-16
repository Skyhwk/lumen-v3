<?php

namespace App\Models;

class GaAssetRoom extends Sector
{
    protected $table = 'ga_asset_rooms';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(GaAssetLocation::class, 'location_id');
    }
}
