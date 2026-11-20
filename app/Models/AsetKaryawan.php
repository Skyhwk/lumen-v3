<?php

namespace App\Models;

use App\Models\Sector;

class AsetKaryawan extends Sector
{
    protected $table = 'aset_karyawan';

    protected $fillable = [
        'master_karyawan_id',
        'new_recruitment_id',
        'master_aset_karyawan_id',
        'rfid_card_id',
        'qty',
        'satuan',
        'ga_asset_id',
        'ga_link_status',
        'ga_linked_at',
        'ga_linked_by',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'is_active',
    ];

    protected $casts = [
        'qty' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'master_karyawan_id');
    }

    public function masterAset()
    {
        return $this->belongsTo(MasterAsetKaryawan::class, 'master_aset_karyawan_id');
    }

    public function rfidCard()
    {
        return $this->belongsTo(RfidCard::class, 'rfid_card_id');
    }

    public function recruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public function gaAsset()
    {
        return $this->belongsTo(GaAsset::class, 'ga_asset_id');
    }
}
