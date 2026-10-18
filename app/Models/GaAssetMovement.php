<?php

namespace App\Models;

class GaAssetMovement extends Sector
{
    public const KIND_ROOM = 'room';
    public const KIND_BRANCH = 'branch';

    protected $table = 'ga_asset_movements';

    protected $guarded = ['id'];

    protected $casts = [
        'asset_id' => 'integer',
        'from_branch_id' => 'integer',
        'to_branch_id' => 'integer',
        'from_location_id' => 'integer',
        'to_location_id' => 'integer',
        'from_room_id' => 'integer',
        'to_room_id' => 'integer',
        'moved_at' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(GaAsset::class, 'asset_id');
    }

    public function fromBranch()
    {
        return $this->belongsTo(MasterCabang::class, 'from_branch_id');
    }

    public function toBranch()
    {
        return $this->belongsTo(MasterCabang::class, 'to_branch_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(GaAssetLocation::class, 'from_location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(GaAssetLocation::class, 'to_location_id');
    }

    public function fromRoom()
    {
        return $this->belongsTo(GaAssetRoom::class, 'from_room_id');
    }

    public function toRoom()
    {
        return $this->belongsTo(GaAssetRoom::class, 'to_room_id');
    }
}
