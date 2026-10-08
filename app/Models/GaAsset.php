<?php

namespace App\Models;

class GaAsset extends Sector
{
    public const RECORD_UNIT = 'unit';
    public const RECORD_COMPONENT = 'component';

    public const PUBLICATION_DRAFT = 'draft';
    public const PUBLICATION_PUBLISHED = 'published';
    public const PUBLICATION_ARCHIVED = 'archived';

    public const VERIFICATION_NEEDS_REVIEW = 'needs_review';
    public const VERIFICATION_VERIFIED = 'verified';

    public const LIFECYCLE_ACTIVE = 'active';
    public const LIFECYCLE_RETIRED = 'retired';
    public const LIFECYCLE_DISPOSED = 'disposed';

    public const USAGE_UNKNOWN = 'unknown';

    public const CONDITIONS = [
        'normal',
        'kendala',
        'rusak',
        'rusak_berat',
        'hilang',
        'transfer',
        'unknown',
    ];

    public const ACQUISITION_ORIGINS = [
        'new',
        'second',
        'takeover',
        'unknown',
    ];

    protected $table = 'ga_assets';

    protected $fillable = [
        'public_id',
        'asset_code',
        'record_kind',
        'parent_asset_id',
        'sub_kategori_aset_id',
        'kategori_aset_id',
        'name',
        'brand',
        'model_spec',
        'cs_code',
        'cs_sequence',
        'branch_id',
        'location_id',
        'room_id',
        'pic_employee_id',
        'department_note',
        'acquisition_origin',
        'condition',
        'lifecycle_state',
        'usage_state',
        'publication_state',
        'verification_state',
        'label_legacy',
        'condition_note',
        'version',
        'archived_at',
        'archived_by',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'parent_asset_id' => 'integer',
        'sub_kategori_aset_id' => 'integer',
        'kategori_aset_id' => 'integer',
        'cs_sequence' => 'integer',
        'branch_id' => 'integer',
        'location_id' => 'integer',
        'room_id' => 'integer',
        'pic_employee_id' => 'integer',
        'version' => 'integer',
        'is_active' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function components()
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    public function subKategori()
    {
        return $this->belongsTo(MasterSubKategoriAset::class, 'sub_kategori_aset_id');
    }

    public function kategori()
    {
        return $this->belongsTo(MasterKategoriAset::class, 'kategori_aset_id');
    }

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

    public function pic()
    {
        return $this->belongsTo(MasterKaryawan::class, 'pic_employee_id');
    }

    public function acquisition()
    {
        return $this->hasOne(GaAssetAcquisition::class, 'asset_id');
    }

    public function identifiers()
    {
        return $this->hasMany(GaAssetIdentifier::class, 'asset_id');
    }

    public function attachments()
    {
        return $this->hasMany(GaAssetAttachment::class, 'asset_id');
    }

    public function events()
    {
        return $this->hasMany(GaAssetEvent::class, 'asset_id');
    }
}
