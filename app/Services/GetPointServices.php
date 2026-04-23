<?php

namespace App\Services;

use App\Models\PointEarning;

use Carbon\Carbon;

class GetPointServices
{
    public function getPoint($customerId)
    {
        $now = Carbon::now();

        $result = PointEarning::query()
            ->selectRaw("
                SUM(
                    CASE 
                        WHEN claim_expired_at > ? 
                        THEN (points - claimed_points - expired_points)
                        ELSE 0
                    END
                ) as active_points,
                SUM(
                    CASE 
                        WHEN tier_expired_at > ? 
                        THEN points
                        ELSE 0
                    END
                ) as tier_points
            ", [$now, $now])
            ->where('customer_id', $customerId)
            ->from((new PointEarning)->getTable())
            ->first();

        return [
            'active_points' => (int) ($result->active_points ?? 0),
            'tier_points'   => (int) ($result->tier_points ?? 0),
        ];
    }
}
