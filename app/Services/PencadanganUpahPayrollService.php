<?php

namespace App\Services;

use App\Models\PencadanganUpah;

class PencadanganUpahPayrollService
{
    public const TIPE_SETELAH_POTONGAN = 'setelah_potongan';
    public const TIPE_SETELAH_KONTRAK = 'setelah_kontrak';

    /** SQL: fase potong (tenor_berjalan negatif). */
    public static function sqlDeductionPhase(): string
    {
        return '(pencadangan_upah.tenor_berjalan < 0)';
    }

    public static function sqlTipeSetelahKontrak(): string
    {
        return 'COALESCE(pencadangan_upah.tipe_pengembalian, "' . self::TIPE_SETELAH_POTONGAN . '") = "' . self::TIPE_SETELAH_KONTRAK . '"';
    }

    public static function sqlEmployeeIsContract(): string
    {
        return 'master_karyawan.status_karyawan = "Contract"';
    }

    /**
     * SQL: fase pengembalian ke payroll.
     * Setelah kontrak: cek Contract hanya saat mulai (tenor_berjalan = 0).
     * Sudah jalan (tenor_berjalan > 0) tidak dicek lagi.
     */
    public static function sqlRefundPhase(): string
    {
        return '(
            (pencadangan_upah.tenor_berjalan > 0 AND pencadangan_upah.tenor_berjalan <= pencadangan_upah.tenor)
            OR (
                pencadangan_upah.tenor_berjalan = 0
                AND ' . self::sqlTipeSetelahKontrak() . '
                AND ' . self::sqlEmployeeIsContract() . '
            )
        )';
    }

    /** SQL: menunggu Contract setelah potong selesai (belum mulai balik). */
    public static function sqlWaitingContractPhase(): string
    {
        return '(
            pencadangan_upah.tenor_berjalan = 0
            AND ' . self::sqlTipeSetelahKontrak() . '
            AND master_karyawan.status_karyawan <> "Contract"
        )';
    }

    /** Nominal kolom preview showData: negatif = potong, positif = balik, 0 = tidak ada efek bulan ini. */
    public static function sqlPreviewAmount(): string
    {
        return 'CASE
            WHEN ' . self::sqlDeductionPhase() . ' THEN -pencadangan_upah.nominal
            WHEN ' . self::sqlRefundPhase() . ' THEN pencadangan_upah.nominal
            ELSE 0
        END';
    }

    /** Kolom agregat showData — hindari SUM dobel saat join kasbon/denda/bonus. */
    public static function sqlShowDataPencadanganColumn(): string
    {
        $preview = self::sqlPreviewAmount();

        return 'ROUND(IFNULL(COALESCE(MAX(payroll.pencadangan_upah), MAX(' . $preview . ')), 0), 0)';
    }

    public static function sqlShowDataThpRefundAdd(): string
    {
        return 'IFNULL(MAX(CASE WHEN ' . self::sqlRefundPhase() . ' THEN pencadangan_upah.nominal ELSE 0 END), 0)';
    }

    public static function sqlShowDataThpDeductionSub(): string
    {
        return 'IFNULL(MAX(CASE WHEN ' . self::sqlDeductionPhase() . ' THEN pencadangan_upah.nominal ELSE 0 END), 0)';
    }

    public static function normalizeTipe(?string $tipe): string
    {
        $tipe = strtolower(trim((string) $tipe));
        if ($tipe === self::TIPE_SETELAH_KONTRAK) {
            return self::TIPE_SETELAH_KONTRAK;
        }

        return self::TIPE_SETELAH_POTONGAN;
    }

    public static function isContractStatus(?string $statusKaryawan): bool
    {
        return strcasecmp(trim((string) $statusKaryawan), 'Contract') === 0;
    }

    /**
     * Setelah payroll disubmit: maju fase potong / tunggu kontrak / pengembalian.
     */
    public static function advanceOnPayrollSubmit(PencadanganUpah $deposit, ?string $employeeStatus): bool
    {
        $tenor = (int) $deposit->tenor;
        $tenorBerjalan = (int) $deposit->tenor_berjalan;
        $tipe = self::normalizeTipe($deposit->tipe_pengembalian ?? null);
        $changed = false;

        if ($tenorBerjalan < 0) {
            if ($tenorBerjalan === -1) {
                if ($tipe === self::TIPE_SETELAH_KONTRAK) {
                    $deposit->tenor_berjalan = 0;
                } else {
                    $deposit->tenor_berjalan = 1;
                }
                if ((float) $deposit->nominal_berjalan < 0) {
                    $deposit->nominal_berjalan = abs((float) $deposit->nominal_berjalan);
                }
            } else {
                $deposit->tenor_berjalan = $tenorBerjalan + 1;
            }
            $changed = true;
        } elseif ($tenorBerjalan === 0 && $tipe === self::TIPE_SETELAH_KONTRAK) {
            if (!self::isContractStatus($employeeStatus)) {
                return false;
            }
            $deposit->tenor_berjalan = 1;
            $changed = true;
        } elseif ($tenorBerjalan > 0 && $tenorBerjalan <= $tenor) {
            if ($tenorBerjalan >= $tenor) {
                $deposit->status = 'END';
            } else {
                $deposit->tenor_berjalan = $tenorBerjalan + 1;
            }
            $changed = true;
        }

        return $changed;
    }

    /**
     * Batalkan efek submit payroll pada pencadangan (rollback satu langkah).
     */
    public static function revertOnPayrollCancel(PencadanganUpah $deposit): bool
    {
        $tenor = (int) $deposit->tenor;
        $tenorBerjalan = (int) $deposit->tenor_berjalan;
        $tipe = self::normalizeTipe($deposit->tipe_pengembalian ?? null);

        if ($deposit->status === 'END') {
            $deposit->status = 'ONGOING';
            $deposit->tenor_berjalan = $tenor;

            return true;
        }

        if ($tenorBerjalan > 1 && $tenorBerjalan <= $tenor) {
            $deposit->tenor_berjalan = $tenorBerjalan - 1;

            return true;
        }

        if ($tenorBerjalan === 1) {
            if ($tipe === self::TIPE_SETELAH_KONTRAK) {
                $deposit->tenor_berjalan = 0;
            } else {
                $deposit->tenor_berjalan = -1;
                $deposit->nominal_berjalan = -abs((float) $deposit->nominal_berjalan);
            }

            return true;
        }

        if ($tenorBerjalan === 0 && $tipe === self::TIPE_SETELAH_KONTRAK) {
            $deposit->tenor_berjalan = -1;
            $deposit->nominal_berjalan = -abs((float) $deposit->nominal_berjalan);

            return true;
        }

        if ($tenorBerjalan < 0 && $tenorBerjalan > -$tenor) {
            $deposit->tenor_berjalan = $tenorBerjalan - 1;

            return true;
        }

        return false;
    }
}
