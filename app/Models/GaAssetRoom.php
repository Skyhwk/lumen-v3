<?php

namespace App\Models;

class GaAssetRoom extends Sector
{
    protected $table = 'ga_asset_rooms';

    protected $fillable = [
        'location_id',
        'code',
        'name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'location_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(GaAssetLocation::class, 'location_id');
    }
}
