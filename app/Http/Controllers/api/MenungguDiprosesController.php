<?php

namespace App\Http\Controllers\api;

use App\Models\ClaimRewardHeader;
use App\Models\ClaimRewardDetail;
use App\Models\KatalogReward;
use App\Services\ClaimPointsServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class MenungguDiprosesController extends ClaimRewardController
{
    public function index(Request $request)
    {
        $request->merge(['status' => 'pending_approved']);
        return parent::index($request);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->storeRules());

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        $claim = DB::connection('portal_customer')->transaction(function () use ($request) {
            $items = collect($request->items ?? [])
                ->map(function ($item) {
                    return is_array($item) ? $item : (array) $item;
                })
                ->values();

            $header = ClaimRewardHeader::create([
                'claim_code' => $request->claim_code ?: $this->generateClaimCode(),
                'customer_id' => $request->customer_id,
                'customer_code' => $request->customer_code,
                'customer_name' => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'address' => $request->address,
                'customer_note' => $request->customer_note,
                'internal_note' => $request->internal_note,
                'status' => self::STATUS_PENDING,
                'meta' => [
                    'source' => $request->source ?: 'portal_customer',
                ],
                'created_by' => $request->created_by ?: ($this->karyawan ?? null),
                'created_at' => Carbon::now()->addHours(7),
                'updated_by' => $request->created_by ?: ($this->karyawan ?? null),
                'updated_at' => Carbon::now()->addHours(7),
                'is_active' => true,
            ]);

            $totalQty = 0;
            $totalPoints = 0;

            foreach ($items as $item) {
                $reward = KatalogReward::findOrFail($item['reward_id']);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $unitPoints = (int) $reward->price;
                $detailTotalPoints = $unitPoints * $qty;

                ClaimRewardDetail::create([
                    'header_id' => $header->id,
                    'reward_id' => $reward->id,
                    'reward_title' => $reward->title,
                    'reward_category' => $reward->category,
                    'variant_name' => $item['variant_name'] ?? null,
                    'qty' => $qty,
                    'unit_points' => $unitPoints,
                    'total_points' => $detailTotalPoints,
                    'reward_snapshot' => [
                        'title' => $reward->title,
                        'category' => $reward->category,
                        'price' => (int) $reward->price,
                        'purchase_price' => (int) ($reward->purchase_price ?? 0),
                        'gallery' => $reward->gallery ?? [],
                        'variants' => $reward->variants ?? [],
                    ],
                    'created_by' => $request->created_by ?: ($this->karyawan ?? null),
                    'created_at' => Carbon::now()->addHours(7)->format('Y-m-d H:i:s'),
                    'updated_by' => $request->created_by ?: ($this->karyawan ?? null),
                    'updated_at' => Carbon::now()->addHours(7)->format('Y-m-d H:i:s'),
                    'is_active' => true,
                ]);

                $totalQty += $qty;
                $totalPoints += $detailTotalPoints;
            }

            $header->total_qty = $totalQty;
            $header->total_points = $totalPoints;
            $header->save();

            return $header->fresh(['details.reward']);
        });

        $this->notifyInternalPendingClaim($claim);

        return response()->json([
            'data' => $this->transformClaim($claim),
            'status' => 200,
            'message' => 'Claim reward berhasil dibuat',
        ]);
    }

    public function approve(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_PENDING], self::STATUS_APPROVED, function ($claim, $request) {
            $claim->approved_by = $this->karyawan ?? $request->approved_by ?? null;
            $claim->approved_at = Carbon::now();
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
            $this->incrementRewardSold($claim);
        }, 'Claim reward disetujui dan siap ditindaklanjuti.', 'claim_reward_approved');
    }

    public function reject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer'],
            'note' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
                'status' => 422,
            ], 422);
        }

        return $this->handleStatusUpdate($request, [self::STATUS_PENDING, self::STATUS_PROCESSED], self::STATUS_REJECTED, function ($claim, $request) {
            $claim->rejected_by = $this->karyawan ?? $request->rejected_by ?? null;
            $claim->rejected_at = Carbon::now();
            $claim->reject_reason = $request->note;
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);

            if ($claim->claim_ids) {
                (new ClaimPointsServices())->refundClaimPoints($claim->claim_ids);
            }
        }, 'Claim reward ditolak.', 'claim_reward_rejected');
    }
}
