<?php

namespace App\Services;

use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;

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
