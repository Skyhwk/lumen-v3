<?php

namespace App\Models;

class GaAssetIdentifier extends Sector
{
    public const SCHEME_CS_CODE = 'cs_code';
    public const SCHEME_LEGACY_EXCEL = 'legacy_excel';
    public const SCHEME_SERIAL = 'serial';
    public const SCHEME_OTHER = 'other';
    public const SCHEME_COMPONENT_LABEL = 'component_label';

    protected $table = 'ga_asset_identifiers';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'scheme',
        'value_raw',
        'value_normalized',
        'is_primary',
        'source_ref',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'is_primary' => 'boolean',
    ];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }
}
