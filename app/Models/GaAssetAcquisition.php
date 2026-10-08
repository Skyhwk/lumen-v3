<?php

namespace App\Models;

class GaAssetAcquisition extends Sector
{
    public const DATE_PRECISIONS = [
        'full',
        'month_year',
        'year_only',
        'partial_dmy',
        'unknown',
    ];

    public const VALUE_BASES = [
        'unknown',
        'standalone',
        'included_in_parent',
        'package_owner',
    ];

    protected $table = 'ga_asset_acquisitions';

    protected $primaryKey = 'asset_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'acquisition_date',
        'date_precision',
        'date_raw_d',
        'date_raw_m',
        'date_raw_y',
        'amount',
        'currency',
        'value_basis',
        'useful_life_years',
        'accurate_note',
        'source_note',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'acquisition_date' => 'date',
        'date_raw_d' => 'integer',
        'date_raw_m' => 'integer',
        'date_raw_y' => 'integer',
        'amount' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }
}
