<?php

namespace App\Services;

use App\Models\MasterSallary;

class MasterSallaryNikSyncService
{
    /**
     * Saat NIK karyawan berubah, samakan NIK di master_sallary aktif
     * dan nonaktifkan duplikat dari NIK lama.
     */
    public static function syncOnNikChange(int $idKaryawan, string $namaLengkap, string $oldNik, string $newNik, string $updatedBy): void
    {
        if ($oldNik === $newNik) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        MasterSallary::where('is_active', true)
            ->where('id_karyawan', $idKaryawan)
            ->update([
                'nik_karyawan' => $newNik,
                'karyawan' => $namaLengkap,
                'updated_at' => $timestamp,
                'updated_by' => $updatedBy,
            ]);

        MasterSallary::where('is_active', true)
            ->whereNull('id_karyawan')
            ->where('nik_karyawan', $oldNik)
            ->update([
                'id_karyawan' => $idKaryawan,
                'nik_karyawan' => $newNik,
                'karyawan' => $namaLengkap,
                'updated_at' => $timestamp,
                'updated_by' => $updatedBy,
            ]);

        MasterSallary::where('is_active', true)
            ->where('id_karyawan', $idKaryawan)
            ->orderByDesc('created_at')
            ->get()
            ->skip(1)
            ->each(function ($duplicate) use ($timestamp, $updatedBy) {
                $duplicate->is_active = false;
                $duplicate->updated_at = $timestamp;
                $duplicate->updated_by = $updatedBy;
                $duplicate->save();
            });
    }

    /**
     * Rapikan data lama: satu karyawan hanya boleh punya satu master_sallary aktif (NIK terbaru).
     */
    public static function reconcileDuplicates(int $idKaryawan, string $namaLengkap, string $currentNik, string $updatedBy): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $activeRecords = MasterSallary::where('is_active', true)
            ->where(function ($query) use ($idKaryawan, $namaLengkap) {
                $query->where('id_karyawan', $idKaryawan)
                    ->orWhere(function ($query) use ($namaLengkap) {
                        $query->whereNull('id_karyawan')
                            ->where('karyawan', $namaLengkap);
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        if ($activeRecords->count() <= 1) {
            $record = $activeRecords->first();
            if ($record) {
                $record->id_karyawan = $idKaryawan;
                $record->nik_karyawan = $currentNik;
                $record->karyawan = $namaLengkap;
                $record->updated_at = $timestamp;
                $record->updated_by = $updatedBy;
                $record->save();
            }

            return;
        }

        $keep = $activeRecords->first();
        $keep->id_karyawan = $idKaryawan;
        $keep->nik_karyawan = $currentNik;
        $keep->karyawan = $namaLengkap;
        $keep->updated_at = $timestamp;
        $keep->updated_by = $updatedBy;
        $keep->save();

        foreach ($activeRecords->skip(1) as $duplicate) {
            $duplicate->is_active = false;
            $duplicate->updated_at = $timestamp;
            $duplicate->updated_by = $updatedBy;
            $duplicate->save();
        }
    }
}
