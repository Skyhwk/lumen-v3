<?php

namespace App\Models;

class GaAssetImportBatch extends Sector
{
    protected $table = 'ga_asset_import_batches';

    protected $guarded = ['id'];

    protected $casts = [
        'is_committed' => 'boolean',
    ];

    public function errors()
    {
        return $this->hasMany(GaAssetImportError::class, 'batch_id');
    }
}
