<?php

namespace App\Services;

class FeeSalesMutasiAligner
{
    /**
     * Bagi total fee yang benar ke mutasi yang sudah ada (proporsional, jumlah pas).
     *
     * @param array<int, array{id:int, amount:float}> $rows
     * @return array<int, float>
     */
    public static function distributeAmounts(array $rows, float $targetTotal): array
    {
        if ($rows === []) {
            return [];
        }

        $targetTotal = round($targetTotal, 2);
        $currentSum = round(array_sum(array_column($rows, 'amount')), 2);

        if ($currentSum <= 0) {
            $out = [];
            foreach ($rows as $i => $row) {
                $out[$row['id']] = $i === count($rows) - 1 ? $targetTotal : 0.0;
            }

            return $out;
        }

        if (abs($currentSum - $targetTotal) < 0.01) {
            $out = [];
            foreach ($rows as $row) {
                $out[$row['id']] = round($row['amount'], 2);
            }

            return $out;
        }

        $factor = $targetTotal / $currentSum;
        $out = [];
        $allocated = 0.0;
        $lastIndex = count($rows) - 1;

        foreach ($rows as $i => $row) {
            if ($i === $lastIndex) {
                $out[$row['id']] = round($targetTotal - $allocated, 2);
            } else {
                $part = round($row['amount'] * $factor, 2);
                $out[$row['id']] = $part;
                $allocated += $part;
            }
        }

        return $out;
    }
}
