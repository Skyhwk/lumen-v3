<?php

namespace App\Services\GaAsset;

use App\Models\GaAsset;
use App\Models\GaAssetMovement;
use App\Models\GaAssetRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GaAssetMovementService
{
    public function transfer(GaAsset $asset, int $toRoomId, ?string $note, string $actor, string $source = 'manual', ?string $movedAt = null): GaAssetMovement
    {
        $room = GaAssetRoom::with('location')->where('id', $toRoomId)->where('is_active', 1)->first();
        if (!$room || !$room->location) {
            throw new \InvalidArgumentException('Ruang tujuan tidak aktif atau tidak ditemukan.');
        }

        $asset->loadMissing(['room', 'location']);
        $toBranchId = (int) $room->location->branch_id;
        $toLocationId = (int) $room->location_id;
        $fromBranchId = $asset->branch_id ? (int) $asset->branch_id : null;
        $fromLocationId = $asset->location_id ? (int) $asset->location_id : null;
        $fromRoomId = $asset->room_id ? (int) $asset->room_id : null;

        if ($fromRoomId === (int) $room->id) {
            throw new \InvalidArgumentException('Aset sudah berada di ruang tersebut.');
        }

        $when = $movedAt ? Carbon::parse($movedAt) : Carbon::now();
        $kind = ($fromBranchId && $fromBranchId !== $toBranchId) ? 'branch' : 'room';

        return DB::transaction(function () use ($asset, $room, $note, $actor, $source, $when, $kind, $fromBranchId, $toBranchId, $fromLocationId, $toLocationId, $fromRoomId) {
            $movement = GaAssetMovement::create([
                'asset_id' => $asset->id,
                'movement_kind' => $kind,
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $toBranchId,
                'from_location_id' => $fromLocationId,
                'to_location_id' => $toLocationId,
                'from_room_id' => $fromRoomId,
                'to_room_id' => $room->id,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : null,
                'source' => $source,
                'created_by' => $actor,
                'moved_at' => $when,
            ]);

            $asset->update([
                'branch_id' => $toBranchId,
                'location_id' => $toLocationId,
                'room_id' => $room->id,
                'updated_by' => $actor,
            ]);

            return $movement;
        });
    }

    public function recordImportedMove(GaAsset $asset, ?int $fromRoomId, int $toRoomId, ?string $movedAt, string $actor): void
    {
        if ($fromRoomId && $fromRoomId === $toRoomId) {
            return;
        }

        $date = $movedAt ? substr($movedAt, 0, 10) : null;
        $exists = GaAssetMovement::query()
            ->where('asset_id', $asset->id)
            ->where('to_room_id', $toRoomId)
            ->where('source', 'import')
            ->when($date, function ($query) use ($date) {
                $query->whereDate('moved_at', $date);
            })
            ->exists();
        if ($exists) {
            return;
        }

        if ($fromRoomId) {
            $asset->room_id = $fromRoomId;
            $fromRoom = GaAssetRoom::with('location')->find($fromRoomId);
            if ($fromRoom && $fromRoom->location) {
                $asset->location_id = $fromRoom->location_id;
                $asset->branch_id = $fromRoom->location->branch_id;
            }
        } else {
            $asset->room_id = null;
        }

        $this->transfer($asset, $toRoomId, 'Import Excel', $actor, 'import', $movedAt ?: null);
    }
}
