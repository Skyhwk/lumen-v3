<?php

namespace App\Support;

use App\Models\OrderDetail;

class FdlOrderDetailNoSampelSync
{
    /**
     * Setelah FDL rename no_sampel: pertahankan tanggal_terima & selaraskan order_detail.no_sampel.
     */
    public static function afterRename(string $noSampelLama, string $noSampelBaru): void
    {
        $noSampelLama = trim($noSampelLama);
        $noSampelBaru = trim($noSampelBaru);

        if ($noSampelLama === '' || $noSampelBaru === '') {
            return;
        }

        $orderDetailLama = OrderDetail::where('no_sampel', $noSampelLama)->first();
        if (!$orderDetailLama) {
            return;
        }

        $tanggalTerima = $orderDetailLama->tanggal_terima;

        $orderDetailBaru = OrderDetail::where('no_sampel', $noSampelBaru)
            ->where('is_active', 1)
            ->first();

        if ($orderDetailBaru && (int) $orderDetailBaru->id !== (int) $orderDetailLama->id) {
            OrderDetail::where('id', $orderDetailBaru->id)->update([
                'tanggal_terima' => $tanggalTerima,
            ]);
            $orderDetailLama->tanggal_terima = null;
            $orderDetailLama->save();

            return;
        }

        $orderDetailLama->no_sampel = $noSampelBaru;
        $orderDetailLama->save();
    }
}
