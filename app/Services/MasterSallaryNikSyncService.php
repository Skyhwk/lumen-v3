<?php

namespace App\Services;

use App\Models\MasterSallary;

class MasterSallaryNikSyncService
{
    /**
     * Saat NIK karyawan berubah, samakan NIK di master_sallary aktif
     * dan nonaktifkan duplikat dari NIK lama.
     */
    public static function syncOnNikChange(string $namaLengkap, string $oldNik, string $newNik, string $updatedBy): void
    {
        if ($oldNik === $newNik) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');

        MasterSallary::where('is_active', true)
            ->where('nik_karyawan', $oldNik)
            ->update([
                'nik_karyawan' => $newNik,
                'karyawan' => $namaLengkap,
                'updated_at' => $timestamp,
                'updated_by' => $updatedBy,
            ]);

        MasterSallary::where('is_active', true)
            ->where('karyawan', $namaLengkap)
            ->where('nik_karyawan', '!=', $newNik)
            ->update([
                'is_active' => false,
                'updated_at' => $timestamp,
                'updated_by' => $updatedBy,
            ]);
    }

    /**
     * Rapikan data lama: satu karyawan hanya boleh punya satu master_sallary aktif (NIK terbaru).
     */
    public static function reconcileDuplicates(string $namaLengkap, string $currentNik, string $updatedBy): void
    {
        $timestamp = date('Y-m-d H:i:s');

        $activeRecords = MasterSallary::where('is_active', true)
            ->where('karyawan', $namaLengkap)
            ->orderByDesc('created_at')
            ->get();

        if ($activeRecords->count() <= 1) {
            $record = $activeRecords->first();
            if ($record && $record->nik_karyawan !== $currentNik) {
                $record->nik_karyawan = $currentNik;
                $record->updated_at = $timestamp;
                $record->updated_by = $updatedBy;
                $record->save();
            }

            return;
        }

        $keep = $activeRecords->first();
        $keep->nik_karyawan = $currentNik;
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
