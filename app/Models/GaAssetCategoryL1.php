<?php

namespace App\Models;

class GaAssetCategoryL1 extends Sector
{
    protected $table = 'ga_asset_category_l1';

    protected $guarded = ['id'];

    public function children()
    {
        return $this->hasMany(GaAssetCategoryL2::class, 'category_l1_id');
    }
}
