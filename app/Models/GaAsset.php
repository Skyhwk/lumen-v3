<?php

namespace App\Models;

class GaAsset extends Sector
{
    protected $table = 'ga_assets';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'acquisition_cost' => 'decimal:2',
        'useful_life_years' => 'integer',
    ];

    public function cabang()
    {
        return $this->belongsTo(MasterCabang::class, 'branch_id');
    }

    public function location()
    {
        return $this->belongsTo(GaAssetLocation::class, 'location_id');
    }

    public function room()
    {
        return $this->belongsTo(GaAssetRoom::class, 'room_id');
    }

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'kategori_aset_id');
    }

    public function subKategori()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'sub_kategori_aset_id');
    }

    public function pic()
    {
        return $this->belongsTo(MasterKaryawan::class, 'pic_employee_id');
    }

    public function depreciationYears()
    {
        return $this->hasMany(GaAssetDepreciationYear::class, 'asset_id');
    }

    public function currentDepreciation()
    {
        return $this->hasOne(GaAssetDepreciationYear::class, 'asset_id')
            ->where('fiscal_year', (int) date('Y'));
    }
}
