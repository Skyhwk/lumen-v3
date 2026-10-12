<?php

namespace App\Models;

class AssetCategory extends Sector
{
    protected $table = 'asset_categories';

    protected $guarded = ['id'];

    public $timestamps = false;
}
