<?php

namespace App\Helpers;

class DraftLhpRejectHelper
{
    public static function allowedGrades()
    {
        return ['MANAGER', 'SENIOR MANAGER'];
    }

    public static function normalizeGrade($grade)
    {
        return strtoupper(trim(str_replace('_', ' ', (string) ($grade ?? ''))));
    }

    public static function isAllowed($grade)
    {
        return in_array(self::normalizeGrade($grade), self::allowedGrades(), true);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    public static function denyIfNotManager($grade)
    {
        if (self::isAllowed($grade)) {
            return null;
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Reject draft LHP hanya dapat dilakukan oleh Manager atau Senior Manager.',
        ], 403);
    }
}
