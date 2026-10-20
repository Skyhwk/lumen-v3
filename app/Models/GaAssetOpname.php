<?php

namespace App\Models;

class GaAssetOpname extends Sector
{
    protected $table = 'ga_asset_opnames';

    protected $guarded = ['id'];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'branch_id');
    }

    public function lines()
    {
        return $this->hasMany(GaAssetOpnameLine::class, 'opname_id');
    }
}
