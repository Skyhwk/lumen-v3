<?php

namespace App\Services;

use App\Models\{
    MasterKaryawan,
    MasterSallary,
};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PayrollRecordSyncService
{
    /**
     * Tutup gaji aktif saat karyawan keluar. BPJS/PPh/kasbon/pencadangan tidak di-nonaktifkan
     * otomatis agar masih bisa diproses di payroll sampai kewajiban selesai.
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

        foreach ([MasterSallary::class] as $modelClass) {
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

    /** Baris terbaru per karyawan (prioritas is_active = 1) untuk preview payroll. */
    public static function sqlJoinLatestRow(string $table, string $foreignKey = 'id_karyawan'): string
    {
        return "{$table}.id = (SELECT t.id FROM {$table} t WHERE t.{$foreignKey} = master_karyawan.id"
            . ' ORDER BY t.is_active DESC, t.id DESC LIMIT 1)';
    }

    /** Satu baris rekap absensi aktif per karyawan + bulan (abaikan rekap is_active = 0). */
    public static function applyJoinActiveRekapMasukKerja($join, string $bulanYm): void
    {
        $join->on('master_karyawan.id', '=', 'rekap_masuk_kerja.karyawan_id')
            ->whereRaw(
                'rekap_masuk_kerja.id = (SELECT t.id FROM rekap_masuk_kerja t WHERE t.karyawan_id = master_karyawan.id'
                . ' AND t.bulan = ? AND t.is_active = 1 ORDER BY t.id DESC LIMIT 1)',
                [$bulanYm]
            );
    }

    /** Join utang/cicilan: aktif, atau non-aktif tapi masih ONGOING (mis. setelah NA manual). */
    public static function applyJoinPayrollDebtFilter($join, string $table, string $balanceField): void
    {
        $join->where(function ($q) use ($table, $balanceField) {
            $q->where("{$table}.is_active", true)
                ->orWhere(function ($q) use ($table, $balanceField) {
                    $q->where("{$table}.is_active", false)
                        ->whereRaw('master_karyawan.is_active = 0')
                        ->where(function ($status) use ($table) {
                            $status->where("{$table}.status", 'ONGOING')
                                ->orWhereNull("{$table}.status");
                        })
                        ->where("{$table}.{$balanceField}", '>', 0)
                        ->where("{$table}.nominal_potongan", '>', 0);
                });
        })
            ->where(function ($status) use ($table) {
                $status->where("{$table}.status", 'ONGOING')
                    ->orWhereNull("{$table}.status");
            });
    }

    public static function applyJoinPencadanganFilter($join): void
    {
        $join->where(function ($q) {
            $q->where('pencadangan_upah.is_active', true)
                ->orWhere(function ($q) {
                    $q->where('pencadangan_upah.is_active', false)
                        ->whereRaw('master_karyawan.is_active = 0')
                        ->where(function ($status) {
                            $status->where('pencadangan_upah.status', 'ONGOING')
                                ->orWhereNull('pencadangan_upah.status');
                        });
                });
        })
            ->where(function ($status) {
                $status->where('pencadangan_upah.status', 'ONGOING')
                    ->orWhereNull('pencadangan_upah.status');
            });
    }

    public static function applyJoinBonusFilter($join): void
    {
        $join->where(function ($q) {
            $q->where('bonus_karyawan.is_active', true)
                ->orWhere(function ($q) {
                    $q->where('bonus_karyawan.is_active', false)
                        ->whereRaw('master_karyawan.is_active = 0');
                });
        });
    }

    /**
     * Sumber potongan payroll (submit).
     *
     * @param  bool|null  $employeeIsActive  Wajib untuk pencadangan: karyawan aktif → hanya baris master is_active=1.
     */
    public static function scopePayrollDeductionSource(
        Builder $query,
        ?string $balanceField = null,
        ?string $periodField = null,
        ?string $periode = null,
        ?bool $employeeIsActive = null
    ): Builder {
        if ($periodField !== null && $periode !== null) {
            $query->where($periodField, '<=', $periode);
        }

        if ($balanceField === null) {
            self::applyPencadanganMasterActiveFilter($query, $employeeIsActive);
        } else {
            $query->where(function ($q) {
                $q->where('is_active', true)
                    ->orWhere(function ($q) {
                        $q->where('is_active', false)
                            ->where(function ($status) {
                                $status->where('status', 'ONGOING')
                                    ->orWhereNull('status');
                            });
                    });
            });
        }

        $query->where(function ($status) {
            $status->where('status', 'ONGOING')
                ->orWhereNull('status');
        });

        if ($balanceField !== null) {
            $query->where($balanceField, '>', 0)
                ->where('nominal_potongan', '>', 0);
        }

        return $query;
    }

    /**
     * Pencadangan upah: karyawan aktif → hanya baris master is_active=1.
     * Karyawan nonaktif → baris aktif atau nonaktif ONGOING (penyelesaian saat exit).
     */
    private static function applyPencadanganMasterActiveFilter(Builder $query, ?bool $employeeIsActive): void
    {
        if ($employeeIsActive === true) {
            $query->where('is_active', true);

            return;
        }

        $query->where(function ($q) {
            $q->where('is_active', true)
                ->orWhere(function ($q) {
                    $q->where('is_active', false)
                        ->where(function ($status) {
                            $status->where('status', 'ONGOING')
                                ->orWhereNull('status');
                        });
                });
        });
    }

    /**
     * Satu baris sumber potongan untuk submit payroll (lockForUpdate pada query pemanggil).
     * Pencadangan: jika beberapa baris ONGOING, ambil is_active DESC, id DESC (selaras preview).
     * Kasbon/denda: tetap wajib unik — lebih dari satu baris = null (ambigu).
     */
    public static function resolvePayrollDeductionSource(
        Builder $query,
        ?string $balanceField,
        ?string $periodField,
        string $periode,
        ?bool $employeeIsActive = null
    ) {
        $scoped = self::scopePayrollDeductionSource(
            $query,
            $balanceField,
            $periodField,
            $periode,
            $employeeIsActive
        )
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get();

        if ($scoped->isEmpty()) {
            return null;
        }
        if ($scoped->count() === 1) {
            return $scoped->first();
        }
        if ($balanceField !== null) {
            return null;
        }

        return $scoped->first();
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
