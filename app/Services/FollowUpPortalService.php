<?php

namespace App\Services;

use App\Models\FollowUpCustomerAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpPortalService
{
    public function clearExpiredPreviews(): void
    {
        $now = Carbon::now();
        FollowUpCustomerAccount::query()
            ->whereNull('followed_at')
            ->whereNotNull('preview_until')
            ->where('preview_until', '<', $now)
            ->update([
                'preview_by_id' => null,
                'preview_until' => null,
                'updated_at' => $now,
            ]);
    }

    public function activePreviewFor(int $karyawanId): ?FollowUpCustomerAccount
    {
        $this->clearExpiredPreviews();

        $now = Carbon::now();

        return FollowUpCustomerAccount::query()
            ->where('preview_by_id', $karyawanId)
            ->whereNull('followed_at')
            ->whereNotNull('preview_until')
            ->where('preview_until', '>', $now)
            ->orderBy('id')
            ->first();
    }

    public function previewFor(int $karyawanId): ?FollowUpCustomerAccount
    {
        return DB::transaction(function () use ($karyawanId) {
            $this->clearExpiredPreviews();

            $active = FollowUpCustomerAccount::query()
                ->where('preview_by_id', $karyawanId)
                ->whereNull('followed_at')
                ->whereNotNull('preview_until')
                ->where('preview_until', '>', Carbon::now())
                ->lockForUpdate()
                ->first();

            if ($active) {
                return $active;
            }

            return $this->allocatePreview($karyawanId);
        }, 5);
    }

    public function releasePreview(int $karyawanId, ?int $id = null): void
    {
        $now = Carbon::now();
        $query = FollowUpCustomerAccount::query()
            ->where('preview_by_id', $karyawanId)
            ->whereNull('followed_at');

        if ($id !== null && $id > 0) {
            $query->where('id', $id);
        }

        $query->update([
            'preview_by_id' => null,
            'preview_until' => null,
            'updated_at' => $now,
        ]);
    }

    public function submit(int $karyawanId, string $karyawanName, int $id, string $keputusan, string $keterangan): bool
    {
        return DB::transaction(function () use ($karyawanId, $karyawanName, $id, $keputusan, $keterangan) {
            $this->clearExpiredPreviews();
            $now = Carbon::now();

            $row = FollowUpCustomerAccount::query()
                ->where('id', $id)
                ->whereNull('followed_at')
                ->lockForUpdate()
                ->first();

            if (!$row) {
                return false;
            }

            if ((int) $row->preview_by_id !== $karyawanId) {
                return false;
            }

            if ($row->preview_until === null || $row->preview_until <= $now) {
                return false;
            }

            $row->keputusan = $keputusan;
            $row->keterangan = $keterangan;
            $row->followed_by_id = $karyawanId;
            $row->followed_by = $karyawanName;
            $row->followed_at = $now;
            $row->preview_by_id = null;
            $row->preview_until = null;
            $row->assigned_to = null;
            $row->slot = null;
            $row->assigned_at = null;
            $row->updated_at = $now;
            $row->save();

            return true;
        }, 5);
    }

    public function counts(): array
    {
        $base = FollowUpCustomerAccount::query();

        return [
            'pending' => (clone $base)->whereNull('followed_at')->count(),
            'done' => (clone $base)->whereNotNull('followed_at')->count(),
        ];
    }

    public function present(?FollowUpCustomerAccount $row): ?array
    {
        if (!$row) {
            return null;
        }

        $previewUntil = $row->preview_until;
        if ($previewUntil !== null && !$previewUntil instanceof Carbon) {
            $previewUntil = Carbon::parse($previewUntil);
        }

        return [
            'id' => (int) $row->id,
            'id_pelanggan' => $row->id_pelanggan,
            'nama_pelanggan' => $row->nama_pelanggan,
            'wilayah' => $row->wilayah,
            'npwp' => $row->npwp,
            'no_tlp' => $row->no_tlp,
            'email' => $row->email,
            'alamat' => $row->alamat,
            'pic_nama' => $row->pic_nama,
            'pic_jabatan' => $row->pic_jabatan,
            'pic_telepon' => $row->pic_telepon,
            'pic_email' => $row->pic_email,
            'preview_until' => $previewUntil ? $previewUntil->format('Y-m-d H:i:s') : null,
        ];
    }

    private function allocatePreview(int $karyawanId): ?FollowUpCustomerAccount
    {
        $now = Carbon::now();
        $until = $now->copy()->addSeconds($this->previewTtlSeconds());

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $candidate = FollowUpCustomerAccount::query()
                ->whereNull('followed_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('preview_by_id')
                        ->orWhere('preview_until', '<=', $now)
                        ->orWhereNull('preview_until');
                })
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if (!$candidate) {
                return null;
            }

            $updated = FollowUpCustomerAccount::query()
                ->where('id', $candidate->id)
                ->whereNull('followed_at')
                ->where(function ($query) use ($now) {
                    $query->whereNull('preview_by_id')
                        ->orWhere('preview_until', '<=', $now)
                        ->orWhereNull('preview_until');
                })
                ->update([
                    'preview_by_id' => $karyawanId,
                    'preview_until' => $until,
                    'updated_at' => $now,
                ]);

            if ($updated === 1) {
                $candidate->preview_by_id = $karyawanId;
                $candidate->preview_until = $until;
                $candidate->updated_at = $now;

                return $candidate;
            }
        }

        return null;
    }

    private function previewTtlSeconds(): int
    {
        $ttl = (int) env('FOLLOW_UP_PORTAL_PREVIEW_TTL', 3600);

        return max(60, min($ttl, 86400));
    }
}