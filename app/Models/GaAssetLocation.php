<?php

namespace App\Models;

class GaAssetLocation extends Sector
{
    protected $table = 'ga_asset_locations';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'branch_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'branch_id');
    }

    public function rooms()
    {
        return $this->hasMany(GaAssetRoom::class, 'location_id');
    }

    public function assets()
    {
        return $this->hasMany(GaAsset::class, 'location_id');
    }
}
