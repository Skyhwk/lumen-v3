<?php

namespace App\Services;

use App\Models\{
    BpjsKesehatan,
    BpjsTk,
    MasterKaryawan,
    MasterSallary,
    PPH21,
};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PayrollRecordSyncService
{
    /**
     * Nonaktifkan record payroll saat karyawan keluar.
     */
    public static function deactivateForKaryawan(MasterKaryawan $karyawan, string $actor): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $nik = $karyawan->nik_karyawan;
        $payload = [
            'is_active' => false,
            'updated_at' => $timestamp,
            'updated_by' => $actor,
            'deleted_at' => $timestamp,
            'deleted_by' => $actor,
        ];

        foreach ([BpjsKesehatan::class, BpjsTk::class, PPH21::class, MasterSallary::class] as $modelClass) {
            $modelClass::where(function ($query) use ($karyawan, $nik) {
                $query->where('id_karyawan', $karyawan->id)
                    ->orWhere(function ($query) use ($nik) {
                        $query->whereNull('id_karyawan')
                            ->where('nik_karyawan', $nik);
                    });
            })
                ->where('is_active', true)
                ->update($payload);
        }
    }

    /**
     * Hanya tampilkan data payroll karyawan yang masih aktif di master_karyawan.
     */
    public static function scopeActiveKaryawan(Builder $query, string $nikColumn = 'nik_karyawan'): Builder
    {
        return $query->whereExists(function ($subQuery) use ($nikColumn) {
            $subQuery->select(DB::raw(1))
                ->from('master_karyawan')
                ->whereColumn('master_karyawan.nik_karyawan', $nikColumn)
                ->where('master_karyawan.is_active', true);
        });
    }

    /**
     * Hanya tampilkan data payroll karyawan aktif berdasarkan id_karyawan.
     */
    public static function scopeActiveKaryawanById(Builder $query, string $idColumn = 'id_karyawan'): Builder
    {
        return $query->whereNotNull($idColumn)
            ->whereExists(function ($subQuery) use ($idColumn) {
                $subQuery->select(DB::raw(1))
                    ->from('master_karyawan')
                    ->whereColumn('master_karyawan.id', $idColumn)
                    ->where('master_karyawan.is_active', true);
            });
    }
}
