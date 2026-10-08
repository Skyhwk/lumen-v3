<?php

namespace App\Models;

class GaAssetSequence extends Sector
{
    protected $table = 'ga_asset_sequences';

    public $timestamps = false;

    protected $fillable = [
        'sub_kategori_aset_id',
        'last_number',
        'updated_at',
    ];

    protected $casts = [
        'sub_kategori_aset_id' => 'integer',
        'last_number' => 'integer',
    ];

    public function subKategori()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'sub_kategori_aset_id');
    }
}
