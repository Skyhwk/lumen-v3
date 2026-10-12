<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class PayrollDebtService
{
    /** Accept raw API numbers and the existing whole-rupiah grouping formats. */
    public static function amount($value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_string($value)) {
            $value = trim($value);
            if (preg_match('/^-?\d{1,3}([.,])\d{3}(?:\1\d{3})*$/', $value)) {
                $value = str_replace(['.', ','], '', $value);
            } elseif (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
                throw new \DomainException('Format nominal payroll tidak valid.');
            }
        } elseif (!is_int($value) && !is_float($value)) {
            throw new \DomainException('Format nominal payroll tidak valid.');
        }
        $amount = (float) $value;
        if (!is_finite($amount) || abs($amount) > 9999999999999) {
            throw new \DomainException('Nominal payroll di luar batas.');
        }
        return round($amount, 0);
    }

    public static function deduct(Model $debt, string $balanceField, float $amount): void
    {
        $balance = round((float) $debt->$balanceField, 0);
        if ($amount < 0 || $amount > $balance) {
            throw new \DomainException('Potongan melebihi sisa saldo atau bernilai negatif. Muat ulang payroll.');
        }
        if ($amount == 0) {
            return;
        }
        self::setBalance($debt, $balanceField, $balance - $amount);
    }

    public static function restore(Model $debt, string $balanceField, float $amount): void
    {
        if ($amount <= 0) {
            throw new \DomainException('Nominal reversal utang tidak valid.');
        }
        self::setBalance($debt, $balanceField, round((float) $debt->$balanceField, 0) + $amount);
    }

    private static function setBalance(Model $debt, string $balanceField, float $balance): void
    {
        $installment = round((float) $debt->nominal_potongan, 0);
        if ($installment <= 0) {
            throw new \DomainException('Nominal cicilan harus lebih dari nol.');
        }
        $debt->$balanceField = $balance;
        $debt->sisa_tenor = $balance > 0 ? (int) ceil($balance / $installment) : 0;
        $debt->status = $balance > 0 ? 'ONGOING' : 'END';
    }

    /** Whitelisted SQL shared by the displayed deduction and THP. */
    public static function sqlPreviewAmount(string $table): string
    {
        $fields = ['kasbon' => 'sisa_kasbon', 'denda_karyawan' => 'sisa_denda'];
        if (!isset($fields[$table])) {
            throw new \InvalidArgumentException('Unknown payroll debt table.');
        }
        $balance = $fields[$table];
        return 'IFNULL(MAX(CASE WHEN (' . $table . '.status = "ONGOING" OR ' . $table . '.status IS NULL) AND ' . $table . '.' . $balance . ' > 0'
            . ' AND ' . $table . '.nominal_potongan > 0 THEN LEAST(ROUND(' . $table . '.' . $balance . ', 0),'
            . ' ROUND(' . $table . '.nominal_potongan, 0)) ELSE 0 END), 0)';
    }
}
