<?php

namespace App\Models;

class GaAssetCategoryL2 extends Sector
{
    protected $table = 'ga_asset_category_l2';

    protected $guarded = ['id'];

    public function parent()
    {
        return $this->belongsTo(GaAssetCategoryL1::class, 'category_l1_id');
    }
}
