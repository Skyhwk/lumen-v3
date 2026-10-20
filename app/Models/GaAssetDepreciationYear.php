<?php

namespace App\Models;

class GaAssetDepreciationYear extends Sector
{
    protected $table = 'ga_asset_depreciation_yearly';

    protected $guarded = ['id'];

    protected $casts = [
        'fiscal_year' => 'integer',
        'useful_life_years' => 'integer',
        'acquisition_cost' => 'decimal:2',
        'annual_depreciation' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
    ];
}
