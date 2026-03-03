<?php

namespace App\Models;

use App\Models\Sector;

class DataAset extends Sector
{
    protected $table = 'data_aset';

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'id_kategori_aset', 'id');
    }

    public function sub_kategori()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'id_subkategori_aset', 'id');
    }

    public function fixing_histories()
    {
        return $this->hasMany(AsetFixingHistories::class, 'aset_id', 'id');
    }

    public function used_histories()
    {
        return $this->hasMany(AsetUsedHistories::class, 'aset_id', 'id');
    }

    public function damage_histories()
    {
        return $this->hasMany(AsetDamageHistories::class, 'aset_id', 'id');
    }

    public function transfer_histories()
    {
        return $this->hasMany(AsetTransferHistories::class, 'aset_id', 'id');
    }
}