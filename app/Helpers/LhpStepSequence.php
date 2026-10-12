<?php

namespace App\Helpers;

use Carbon\Carbon;

class LhpStepSequence
{
    private const STEP_ORDER = ['order', 'sampling', 'analisa', 'drafting', 'lhp_release'];

    public static function normalize(array $steps): array
    {
        $previousDate = null;

        foreach (self::STEP_ORDER as $key) {
            if (!isset($steps[$key]) || !is_array($steps[$key])) {
                continue;
            }

            $currentDate = self::toDate($steps[$key]['date'] ?? null);
            $steps[$key]['date'] = $currentDate;

            if ($currentDate === null) {
                continue;
            }

            if ($previousDate !== null && $currentDate < $previousDate) {
                $currentDate = $previousDate;
                $steps[$key]['date'] = $previousDate;
            }

            $previousDate = $currentDate;
        }

        return $steps;
    }

    private static function toDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
