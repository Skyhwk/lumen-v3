<?php

namespace App\Http\Controllers\api;

use App\Models\ClaimRewardHeader;
use Illuminate\Http\Request;
use Carbon\Carbon;

class MenungguDikirimController extends ClaimRewardController
{
    public function index(Request $request)
    {
        $request->merge(['status' => 'approved']);
        return parent::index($request);
    }

    public function process(Request $request)
    {
        return $this->handleStatusUpdate($request, [self::STATUS_APPROVED], self::STATUS_PROCESSED, function ($claim, $request) {
            $claim->processed_by = $this->karyawan ?? $request->processed_by ?? null;
            $claim->processed_at = Carbon::now();
            $this->applyShippingData($claim, $request);
            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Claim reward sedang diproses.', 'claim_reward_process');
    }

    public function getShippingCouriers()
    {
        $data = ClaimRewardHeader::query()
            ->select('shipping_courier')
            ->whereNotNull('shipping_courier')
            ->where('shipping_courier', '<>', '')
            ->when($this->hasHeaderColumn('shipping_method'), function ($query) {
                $query->where('shipping_method', 'expedition');
            })
            ->distinct()
            ->orderBy('shipping_courier')
            ->pluck('shipping_courier')
            ->values()
            ->all();

        return response()->json([
            'data' => $data,
            'status' => 200,
            'message' => 'Berhasil mendapatkan data kurir',
        ]);
    }
}
