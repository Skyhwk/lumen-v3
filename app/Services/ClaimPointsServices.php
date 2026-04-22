<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\PointClaimDetail;
use App\Models\PointEarning;
use App\Models\PointClaim;

use Carbon\Carbon;

class ClaimPointsServices
{
    public function claimPoints($customerId, $pointsToUse)
    {
        return DB::transaction(function () use ($customerId, $pointsToUse) {
    
            // 1. Lock semua batch poin yg masih usable
            $batches = PointEarning::where('customer_id', $customerId)
                ->whereRaw('(points - claimed_points - expired_points) > 0')
                ->where('claim_expired_at', '>', Carbon::now())
                ->orderBy('earned_at', 'asc')
                ->lockForUpdate()
                ->get();
    
            $remaining = $pointsToUse;
            $totalUsed = 0;
            $details = [];
    
            foreach ($batches as $batch) {
                if ($remaining <= 0) break;
    
                $available = $batch->points - $batch->claimed_points - $batch->expired_points;
                if ($available <= 0) continue;
    
                $use = min($available, $remaining);
    
                // update claimed_points
                PointEarning::where('id', $batch->id)
                    ->where('id', $batch->id)
                    ->update([
                        'claimed_points' => $batch->claimed_points + $use
                    ]);
    
                $details[] = [
                    'earning_id' => $batch->id,
                    'points_used' => $use
                ];
    
                $remaining -= $use;
                $totalUsed += $use;
            }
    
            // 2. Validasi cukup atau tidak
            if ($remaining > 0) {
                throw new \Exception('Poin tidak cukup');
            }
    
            // 3. Insert header claim
            $claimId = PointClaim::create([
                'customer_id' => $customerId,
                'total_points' => $totalUsed,
                'created_at' => Carbon::now()
            ]);
    
            // 4. Insert detail
            foreach ($details as &$d) {
                $d['claim_id'] = $claimId;
            }
    
            PointClaimDetail::insert($details);
    
            return $claimId;
        });
    }
}
