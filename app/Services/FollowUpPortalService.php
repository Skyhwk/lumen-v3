<?php

namespace App\Services;

use App\Models\FollowUpCustomerAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FollowUpPortalService
{
    public function releaseOpenAssignments(int $karyawanId): void
    {
        FollowUpCustomerAccount::query()
            ->where('assigned_to', $karyawanId)
            ->whereNull('followed_at')
            ->update([
                'assigned_to' => null,
                'slot' => null,
                'assigned_at' => null,
                'updated_at' => Carbon::now(),
            ]);
    }

    public function currentFor(int $karyawanId): ?FollowUpCustomerAccount
    {
        return DB::transaction(function () use ($karyawanId) {
            $open = FollowUpCustomerAccount::query()
                ->where('assigned_to', $karyawanId)
                ->whereNull('followed_at')
                ->lockForUpdate()
                ->get();

            $current = $open->firstWhere('slot', 'current');
            $next = $open->firstWhere('slot', 'next');

            if (!$current && $next) {
                $next->slot = 'current';
                $next->assigned_at = Carbon::now();
                $next->updated_at = Carbon::now();
                $next->save();
                $current = $next;
                $next = null;
            }

            if (!$current) {
                $current = $this->claim($karyawanId, 'current');
            }

            if (!$next) {
                $this->claim($karyawanId, 'next');
            }

            return $current;
        }, 5);
    }

    public function submit(int $karyawanId, string $karyawanName, int $id, string $keputusan, string $keterangan): ?FollowUpCustomerAccount
    {
        return DB::transaction(function () use ($karyawanId, $karyawanName, $id, $keputusan, $keterangan) {
            $current = FollowUpCustomerAccount::query()
                ->where('id', $id)
                ->where('assigned_to', $karyawanId)
                ->where('slot', 'current')
                ->whereNull('followed_at')
                ->lockForUpdate()
                ->first();

            if (!$current) {
                return null;
            }

            $now = Carbon::now();
            $current->keputusan = $keputusan;
            $current->keterangan = $keterangan;
            $current->followed_by_id = $karyawanId;
            $current->followed_by = $karyawanName;
            $current->followed_at = $now;
            $current->slot = null;
            $current->updated_at = $now;
            $current->save();

            $next = FollowUpCustomerAccount::query()
                ->where('assigned_to', $karyawanId)
                ->where('slot', 'next')
                ->whereNull('followed_at')
                ->lockForUpdate()
                ->first();

            if ($next) {
                $next->slot = 'current';
                $next->assigned_at = $now;
                $next->updated_at = $now;
                $next->save();
            } else {
                $next = $this->claim($karyawanId, 'current');
            }

            $this->claim($karyawanId, 'next');

            return $next;
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
        ];
    }

    private function claim(int $karyawanId, string $slot): ?FollowUpCustomerAccount
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $row = FollowUpCustomerAccount::query()
                ->whereNull('assigned_to')
                ->whereNull('followed_at')
                ->orderBy('id')
                ->lock('for update skip locked')
                ->first();

            if (!$row) {
                return null;
            }

            $now = Carbon::now();
            $taken = FollowUpCustomerAccount::query()
                ->where('id', $row->id)
                ->whereNull('assigned_to')
                ->whereNull('followed_at')
                ->update([
                    'assigned_to' => $karyawanId,
                    'slot' => $slot,
                    'assigned_at' => $now,
                    'updated_at' => $now,
                ]);

            if ($taken === 1) {
                $row->assigned_to = $karyawanId;
                $row->slot = $slot;
                $row->assigned_at = $now;
                $row->updated_at = $now;
                return $row;
            }
        }

        return null;
    }
}
