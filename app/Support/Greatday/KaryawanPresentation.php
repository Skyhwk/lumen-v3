<?php

namespace App\Support\Greatday;

use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;

/**
 * Kolom master_karyawan.jabatan / department (string) bentrok dengan relasi homonim.
 * Relasi: jabatan → id_jabatan, divisi/department → id_department.
 */
final class KaryawanPresentation
{
    /**
     * @return string|null
     */
    public static function jabatanLabel(MasterKaryawan $karyawan)
    {
        if ($karyawan->relationLoaded('jabatan')) {
            $rel = $karyawan->getRelation('jabatan');
            if ($rel instanceof MasterJabatan) {
                return $rel->nama_jabatan;
            }
        }

        $attrs = $karyawan->getAttributes();
        if (isset($attrs['jabatan']) && is_string($attrs['jabatan']) && $attrs['jabatan'] !== '') {
            return $attrs['jabatan'];
        }

        if (!empty($karyawan->id_jabatan)) {
            $rel = $karyawan->jabatan()->first();
            if ($rel instanceof MasterJabatan) {
                return $rel->nama_jabatan;
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    public static function divisiLabel(MasterKaryawan $karyawan)
    {
        foreach (['divisi', 'department'] as $relationName) {
            if ($karyawan->relationLoaded($relationName)) {
                $rel = $karyawan->getRelation($relationName);
                if ($rel instanceof MasterDivisi) {
                    return $rel->nama_divisi;
                }
            }
        }

        if (!empty($karyawan->id_department)) {
            $rel = $karyawan->divisi()->first();
            if ($rel instanceof MasterDivisi) {
                return $rel->nama_divisi;
            }
        }

        $attrs = $karyawan->getAttributes();
        if (isset($attrs['department']) && is_string($attrs['department']) && $attrs['department'] !== '') {
            return $attrs['department'];
        }

        return null;
    }
}
