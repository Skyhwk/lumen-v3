<?php

namespace App\Services;

use App\Models\HistoryPerubahanKaryawan;
use App\Models\MasterDivisi;
use App\Models\MasterJabatan;
use App\Models\MasterKaryawan;
use Illuminate\Support\Facades\Schema;

class HistoryPerubahanKaryawanService
{
    public const TRACKED_FIELDS = [
        'status_karyawan',
        'grade',
        'kategori_grade',
        'jabatan',
        'id_jabatan',
        'nik_karyawan',
        'id_cabang',
        'privilage_cabang',
        'department',
        'id_department',
        'atasan_langsung',
    ];

    /**
     * Ambil snapshot field khusus dari instance MasterKaryawan.
     */
    public static function captureSnapshot(MasterKaryawan $karyawan): array
    {
        $idJabatan = $karyawan->id_jabatan !== null && $karyawan->id_jabatan !== ''
            ? (int) $karyawan->id_jabatan
            : null;
        $idDepartment = $karyawan->id_department !== null && $karyawan->id_department !== ''
            ? (int) $karyawan->id_department
            : null;
        $idCabang = $karyawan->id_cabang !== null && $karyawan->id_cabang !== ''
            ? (int) $karyawan->id_cabang
            : null;

        $jabatanLabel = trim((string) ($karyawan->getAttributes()['jabatan'] ?? ''));
        if ($jabatanLabel === '' && $idJabatan) {
            $jabatanLabel = (string) (MasterJabatan::where('id', $idJabatan)->value('nama_jabatan') ?? '');
        }

        $departmentLabel = trim((string) ($karyawan->getAttributes()['department'] ?? ''));
        if ($departmentLabel === '' && $idDepartment) {
            $departmentLabel = (string) (MasterDivisi::where('id', $idDepartment)->value('nama_divisi') ?? '');
        }

        return [
            'status_karyawan' => self::normalizeScalar($karyawan->status_karyawan),
            'grade' => self::normalizeScalar($karyawan->grade),
            'kategori_grade' => self::normalizeScalar($karyawan->kategori_grade),
            'id_jabatan' => $idJabatan,
            'jabatan' => $jabatanLabel !== '' ? $jabatanLabel : null,
            'nik_karyawan' => self::normalizeScalar($karyawan->nik_karyawan),
            'id_cabang' => $idCabang,
            'privilage_cabang' => self::normalizeJsonString($karyawan->privilage_cabang),
            'id_department' => $idDepartment,
            'department' => $departmentLabel !== '' ? $departmentLabel : null,
            'atasan_langsung' => self::normalizeJsonString($karyawan->atasan_langsung),
        ];
    }

    /**
     * Sinkronkan nama jabatan & nama divisi ke payload $dataKaryawan bila id_jabatan / id_department diisi.
     */
    public static function enrichMasterKaryawanLabels(array &$dataKaryawan): void
    {
        if (array_key_exists('id_jabatan', $dataKaryawan)) {
            $idJabatan = $dataKaryawan['id_jabatan'];
            $namaJabatan = $idJabatan
                ? MasterJabatan::where('id', (int) $idJabatan)->value('nama_jabatan')
                : null;
            if (Schema::hasColumn('master_karyawan', 'jabatan') && $namaJabatan !== null) {
                $dataKaryawan['jabatan'] = $namaJabatan;
            }
        }

        if (array_key_exists('id_department', $dataKaryawan)) {
            $idDepartment = $dataKaryawan['id_department'];
            $namaDivisi = $idDepartment
                ? MasterDivisi::where('id', (int) $idDepartment)->value('nama_divisi')
                : null;
            if (Schema::hasColumn('master_karyawan', 'department') && $namaDivisi !== null) {
                $dataKaryawan['department'] = $namaDivisi;
            }
        }
    }

    /**
     * Catat ke tabel history_perubahan_karyawan jika ada minimal 1 dari 10 field khusus yang berubah.
     */
    public static function recordIfChanged(
        MasterKaryawan $karyawanAfter,
        array $beforeSnapshot,
        ?string $actor = null,
        ?string $timestamp = null
    ): ?HistoryPerubahanKaryawan {
        if (!Schema::hasTable('history_perubahan_karyawan')) {
            return null;
        }

        $afterSnapshot = self::captureSnapshot($karyawanAfter);
        $changedFields = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $oldVal = $beforeSnapshot[$field] ?? null;
            $newVal = $afterSnapshot[$field] ?? null;

            if (!self::isSameValue($field, $oldVal, $newVal)) {
                $changedFields[] = $field;
            }
        }

        if ($changedFields === []) {
            return null;
        }

        $now = $timestamp ?: date('Y-m-d H:i:s');

        return HistoryPerubahanKaryawan::create([
            'id_karyawan' => $karyawanAfter->id,
            'nama_lengkap' => $karyawanAfter->nama_lengkap,
            'nik_karyawan_lama' => $beforeSnapshot['nik_karyawan'],
            'nik_karyawan_baru' => $afterSnapshot['nik_karyawan'],
            'status_karyawan_lama' => $beforeSnapshot['status_karyawan'],
            'status_karyawan_baru' => $afterSnapshot['status_karyawan'],
            'grade_lama' => $beforeSnapshot['grade'],
            'grade_baru' => $afterSnapshot['grade'],
            'kategori_grade_lama' => $beforeSnapshot['kategori_grade'],
            'kategori_grade_baru' => $afterSnapshot['kategori_grade'],
            'id_jabatan_lama' => $beforeSnapshot['id_jabatan'],
            'id_jabatan_baru' => $afterSnapshot['id_jabatan'],
            'jabatan_lama' => $beforeSnapshot['jabatan'],
            'jabatan_baru' => $afterSnapshot['jabatan'],
            'id_department_lama' => $beforeSnapshot['id_department'],
            'id_department_baru' => $afterSnapshot['id_department'],
            'department_lama' => $beforeSnapshot['department'],
            'department_baru' => $afterSnapshot['department'],
            'id_cabang_lama' => $beforeSnapshot['id_cabang'],
            'id_cabang_baru' => $afterSnapshot['id_cabang'],
            'privilage_cabang_lama' => $beforeSnapshot['privilage_cabang'],
            'privilage_cabang_baru' => $afterSnapshot['privilage_cabang'],
            'atasan_langsung_lama' => $beforeSnapshot['atasan_langsung'],
            'atasan_langsung_baru' => $afterSnapshot['atasan_langsung'],
            'changed_fields' => $changedFields,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $afterSnapshot,
            'updated_by' => $actor,
            'updated_at' => $now,
            'created_at' => $now,
        ]);
    }

    private static function isSameValue(string $field, $oldVal, $newVal): bool
    {
        if (in_array($field, ['id_jabatan', 'id_department', 'id_cabang'], true)) {
            $a = ($oldVal === null || $oldVal === '') ? null : (int) $oldVal;
            $b = ($newVal === null || $newVal === '') ? null : (int) $newVal;
            return $a === $b;
        }

        if (in_array($field, ['privilage_cabang', 'atasan_langsung'], true)) {
            return self::normalizeJsonArray($oldVal) === self::normalizeJsonArray($newVal);
        }

        return trim((string) ($oldVal ?? '')) === trim((string) ($newVal ?? ''));
    }

    private static function normalizeScalar($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private static function normalizeJsonString($value): ?string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return null;
        }
        if (is_array($value)) {
            return json_encode(array_values($value));
        }
        return (string) $value;
    }

    private static function normalizeJsonArray($value): array
    {
        if ($value === null || $value === '' || $value === 'null') {
            return [];
        }
        $decoded = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($decoded)) {
            return [(string) $value];
        }
        $items = array_map(fn ($v) => (string) $v, $decoded);
        sort($items);
        return array_values($items);
    }
}
