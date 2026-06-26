<?php

namespace App\Services;

use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use Illuminate\Support\Facades\DB;

class KaryawanProfileService
{
    public static function findByName(?string $namaLengkap): ?MasterKaryawan
    {
        if (empty($namaLengkap)) {
            return null;
        }

        return MasterKaryawan::with(['jabatan', 'divisi'])
            ->where('nama_lengkap', $namaLengkap)
            ->where('is_active', true)
            ->first();
    }

    public static function resolveJabatan(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('jabatan')) {
            $jabatanRelation = $employee->getRelation('jabatan');
            if ($jabatanRelation && !empty($jabatanRelation->nama_jabatan)) {
                return $jabatanRelation->nama_jabatan;
            }
        }

        if (!empty($employee->id_jabatan)) {
            $jabatan = MasterJabatan::where('id', $employee->id_jabatan)->where('is_active', true)->first();
            if ($jabatan && !empty($jabatan->nama_jabatan)) {
                return $jabatan->nama_jabatan;
            }
        }

        $jabatanAttr = $employee->getAttributes()['jabatan'] ?? null;
        if (!empty($jabatanAttr) && is_string($jabatanAttr)) {
            return $jabatanAttr;
        }

        return '-';
    }

    public static function resolveDivisi(?MasterKaryawan $employee): string
    {
        if (!$employee) {
            return '-';
        }

        if ($employee->relationLoaded('divisi')) {
            $divisiRelation = $employee->getRelation('divisi');
            if ($divisiRelation && !empty($divisiRelation->nama_divisi)) {
                return $divisiRelation->nama_divisi;
            }
        }

        if (!empty($employee->id_department)) {
            $divisi = MasterDivisi::where('id', $employee->id_department)->where('is_active', true)->first();
            if ($divisi && !empty($divisi->nama_divisi)) {
                return $divisi->nama_divisi;
            }
        }

        $departmentAttr = $employee->getAttributes()['department'] ?? null;
        if (!empty($departmentAttr) && is_string($departmentAttr)) {
            return $departmentAttr;
        }

        return '-';
    }

    public static function applyRequesterDivisiFilter($query, string $keyword, string $createdByColumn = 'purchase_requests.created_by'): void
    {
        $matchingDivisiIds = MasterDivisi::where('is_active', true)
            ->where('nama_divisi', 'like', "%{$keyword}%")
            ->pluck('id');

        $query->whereExists(function ($sub) use ($keyword, $matchingDivisiIds, $createdByColumn) {
            $sub->select(DB::raw(1))
                ->from('master_karyawan as mk')
                ->whereRaw("{$createdByColumn} COLLATE utf8mb4_unicode_ci = mk.nama_lengkap COLLATE utf8mb4_unicode_ci")
                ->where(function ($q) use ($keyword, $matchingDivisiIds) {
                    $q->where('mk.department', 'like', "%{$keyword}%");

                    if ($matchingDivisiIds->isNotEmpty()) {
                        $q->orWhereIn('mk.id_department', $matchingDivisiIds);
                    }

                    $q->orWhereExists(function ($divSub) use ($keyword) {
                        $divSub->select(DB::raw(1))
                            ->from('master_divisi as md')
                            ->whereColumn('mk.id_department', 'md.id')
                            ->where('md.nama_divisi', 'like', "%{$keyword}%");
                    });
                });
        });
    }

    public static function profile(?string $namaLengkap): array
    {
        $employee = self::findByName($namaLengkap);

        return [
            'nama_lengkap' => $namaLengkap ?: '-',
            'jabatan' => self::resolveJabatan($employee),
            'divisi' => self::resolveDivisi($employee),
            'nik_karyawan' => $employee->nik_karyawan ?? '-',
        ];
    }
}
