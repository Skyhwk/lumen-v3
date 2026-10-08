<?php

namespace App\Models;

class GaAssetAttachment extends Sector
{
    public const VISIBILITY_INTERNAL = 'internal';

    protected $table = 'ga_asset_attachments';

    const UPDATED_AT = null;

    protected $fillable = [
        'asset_id',
        'storage_key',
        'original_name',
        'mime',
        'size_bytes',
        'sha256',
        'uploaded_by',
        'visibility',
        'archived_at',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'size_bytes' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }
}
