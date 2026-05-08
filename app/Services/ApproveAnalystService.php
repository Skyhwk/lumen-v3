<?php

namespace App\Services;

use App\Models\HistoryAppReject;
use App\Models\OrderDetail;
use Carbon\Carbon;
use InvalidArgumentException;

class ApproveAnalystService
{
    private string $noSampel;
    private ?string $approvedBy = null;

    public static function noSampel(string $noSampel): self
    {
        $instance = new self();
        $instance->noSampel = $noSampel;

        return $instance;
    }

    public function approvedBy(?string $approvedBy): self
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function menu(string $menu): HistoryAppReject
    {
        if (empty($this->noSampel)) {
            throw new InvalidArgumentException('No sampel harus diisi.');
        }

        if (trim($menu) === '') {
            throw new InvalidArgumentException('Menu harus diisi.');
        }

        $now = Carbon::now();

        $existing = HistoryAppReject::where('no_sampel', $this->noSampel)
            ->where('menu', $menu)
            ->first();

        if ($existing) {
            $existing->approved_at = $now;
            $existing->save();

            return $existing->fresh();
        }

        $orderDetail = OrderDetail::where('no_sampel', $this->noSampel)
            ->where('is_active', true)
            ->first();

        return HistoryAppReject::create([
            'no_lhp' => $orderDetail->cfr ?? null,
            'no_sampel' => $this->noSampel,
            'kategori_2' => $orderDetail->kategori_2 ?? null,
            'kategori_3' => $orderDetail->kategori_3 ?? null,
            'menu' => $menu,
            'status' => 'approve',
            'approved_at' => $now,
            'approved_by' => $this->approvedBy ?? 'SYSTEM',
        ]);
    }
}
