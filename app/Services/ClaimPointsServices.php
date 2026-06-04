<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\PointClaimDetail;
use App\Models\PointEarning;
use App\Models\PointClaim;
use App\Models\Customer\User;
use App\Models\ViewCustomerPoints;
use Carbon\Carbon;
use Exception;

class ClaimPointsServices
{
    public function claimPoints($userId, $pointsToUse)
    {
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('User tidak ditemukan');
        }

        $customerIdArr = $this->normalizeCustomerIds($user->id_pelanggan);

        if (empty($customerIdArr)) {
            throw new \Exception('User tidak memiliki customer yang terkait');
        }

        $totalPoints = ViewCustomerPoints::whereIn('pelanggan_id', $customerIdArr)->sum('points_balance');

        if ($totalPoints < $pointsToUse) {
            throw new \Exception('Poin tidak cukup');
        }

        return DB::transaction(function () use ($customerIdArr, $pointsToUse) {
            // Lock semua batch poin usable lintas customer milik user.
            $batches = PointEarning::whereIn('customer_id', $customerIdArr)
                ->whereRaw('(points - claimed_points - expired_points) > 0')
                ->where('claim_expired_at', '>', Carbon::now())
                ->orderBy('earned_at', 'asc')
                ->lockForUpdate()
                ->get();

            $remaining = $pointsToUse;
            $totalUsed = 0;
            $details = [];
            $usedPointsByCustomer = [];

            foreach ($batches as $batch) {
                if ($remaining <= 0) {
                    break;
                }

                $available = $batch->points - $batch->claimed_points - $batch->expired_points;
                if ($available <= 0) {
                    continue;
                }

                $use = min($available, $remaining);

                PointEarning::where('id', $batch->id)
                    ->update([
                        'claimed_points' => $batch->claimed_points + $use
                    ]);

                $details[] = [
                    'earning_id' => $batch->id,
                    'customer_id' => $batch->customer_id,
                    'points_used' => $use
                ];

                if (!isset($usedPointsByCustomer[$batch->customer_id])) {
                    $usedPointsByCustomer[$batch->customer_id] = 0;
                }

                $usedPointsByCustomer[$batch->customer_id] += $use;
                $remaining -= $use;
                $totalUsed += $use;
            }

            if ($remaining > 0) {
                throw new \Exception('Poin tidak cukup');
            }

            $claimIds = [];

            foreach ($usedPointsByCustomer as $customerId => $usedPoints) {
                $claim = PointClaim::create([
                    'customer_id' => $customerId,
                    'total_points' => $usedPoints,
                    'created_at' => Carbon::now()
                ]);

                $claimIds[] = $claim->id;

                foreach ($details as &$detail) {
                    if (($detail['customer_id'] ?? null) !== $customerId || isset($detail['claim_id'])) {
                        continue;
                    }

                    $detail['claim_id'] = $claim->id;
                }
            }

            $details = array_map(function ($detail) {
                unset($detail['customer_id']);

                return $detail;
            }, $details);

            PointClaimDetail::insert($details);

            return [
                'claim_ids' => $claimIds,
                'total_points' => $totalUsed
            ];
        });
    }

    public function refundClaimPoints($claimIds)
    {
        $claimIds = is_array($claimIds) ? $claimIds : json_decode($claimIds, true);
        $claimIds = array_values(array_filter($claimIds ?: []));

        if (empty($claimIds)) {
            throw new Exception('Data klaim poin tidak ditemukan');
        }

        return DB::transaction(function () use ($claimIds) {
            $details = PointClaimDetail::whereIn('claim_id', $claimIds)->lockForUpdate()->get();

            if ($details->isEmpty()) {
                throw new Exception('Detail klaim poin tidak ditemukan');
            }

            foreach ($details as $detail) {
                $earning = PointEarning::where('id', $detail->earning_id)->lockForUpdate()->first();

                if (!$earning) {
                    throw new Exception('Batch poin tidak ditemukan');
                }

                $earning->claimed_points = max(0, $earning->claimed_points - $detail->points_used);
                $earning->save();
            }

            PointClaimDetail::whereIn('claim_id', $claimIds)->delete();
            PointClaim::whereIn('id', $claimIds)->delete();

            return true;
        });
    }

    private function normalizeCustomerIds($customerIds)
    {
        if (empty($customerIds)) {
            return [];
        }

        $decodedCustomerIds = json_decode($customerIds, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedCustomerIds)) {
            return array_values(array_filter($decodedCustomerIds, function ($customerId) {
                return !is_null($customerId) && $customerId !== '';
            }));
        }

        return [$customerIds];
    }

    public function claimPointsByCustomerid($customerId, $pointsToUse)
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
                $d['claim_id'] = $claimId->id;
            }
    
            PointClaimDetail::insert($details);
    
            return $claimId;
        });
    }
}
