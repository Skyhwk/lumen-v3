<?php

namespace App\Models;

class AssetType extends Sector
{
    protected $table = 'asset_types';

    protected $guarded = ['id'];

    public $timestamps = false;
}
