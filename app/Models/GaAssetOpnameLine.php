<?php

namespace App\Models;

class GaAssetOpnameLine extends Sector
{
    protected $table = 'ga_asset_opname_lines';

    protected $guarded = ['id'];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }

    public function room()
    {
        return $this->belongsTo(GaAssetRoom::class, 'room_id');
    }
}
