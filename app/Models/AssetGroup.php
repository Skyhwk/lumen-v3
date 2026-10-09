<?php

namespace App\Models;

class AssetGroup extends Sector
{
    protected $table = 'asset_groups';

    protected $guarded = ['id'];

    public $timestamps = false;
}
