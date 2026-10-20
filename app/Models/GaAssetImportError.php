<?php

namespace App\Models;

class GaAssetImportError extends Sector
{
    protected $table = 'ga_asset_import_errors';

    protected $guarded = ['id'];

    public $timestamps = false;
}
