<?php

namespace App\Models;

class GaAssetEvent extends Sector
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const ARCHIVED = 'archived';
    public const RESTORED = 'restored';
    public const CS_ALLOCATED = 'cs_allocated';
    public const CS_CORRECTED = 'cs_corrected';
    public const ATTACHMENT_ADDED = 'attachment_added';
    public const ATTACHMENT_REMOVED = 'attachment_removed';

    protected $table = 'ga_asset_events';

    public $timestamps = false;

    protected $fillable = [
        'asset_id',
        'event_type',
        'actor_id',
        'occurred_at',
        'reason',
        'before_json',
        'after_json',
        'request_id',
        'source_ref',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'occurred_at' => 'datetime',
        'before_json' => 'array',
        'after_json' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }
}
