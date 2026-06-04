<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Carbon\Carbon;

class PesananTibaDitujuanController extends ClaimRewardController
{
    public function index(Request $request)
    {
        $request->merge(['status' => 'shipping']);
        return parent::index($request);
    }

    public function delivered(Request $request)
    {
        $this->validate($request, [
            'received_by' => 'required|string|max:255',
            'received_date' => 'required|date',
        ]);

        return $this->handleStatusUpdate($request, [self::STATUS_PROCESSED], self::STATUS_DELIVERED, function ($claim, $request) {
            if ($this->hasHeaderColumn('delivered_by')) {
                $claim->delivered_by = $this->karyawan ?? $request->delivered_by ?? null;
            } else {
                $meta = $claim->meta ?: [];
                $meta['delivered_by'] = $this->karyawan ?? $request->delivered_by ?? null;
                $claim->meta = $meta;
            }

            if ($this->hasHeaderColumn('delivered_at')) {
                $claim->delivered_at = Carbon::now();
            } else {
                $meta = $claim->meta ?: [];
                $meta['delivered_at'] = Carbon::now()->format('Y-m-d H:i:s');
                $claim->meta = $meta;
            }

            // Update meta column with new key received_by and update estimated_received_date
            $meta = $claim->meta ?: [];
            $meta['received_by'] = $request->received_by;
            $meta['received_date'] = $request->received_date;
            $meta['estimated_received_date'] = $request->received_date;
            $claim->meta = $meta;

            $claim->internal_note = $this->mergeNotes($claim->internal_note, $request->note);
        }, 'Pesanan claim reward telah tiba di tujuan (delivered).', 'claim_reward_delivered');
    }
}
