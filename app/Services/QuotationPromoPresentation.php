<?php

namespace App\Services;

class QuotationPromoPresentation
{
    // Presentation only: never save this clone or recalculate payable amounts.
    public static function forPdf($source, $promo = null)
    {
        if (empty($source->promo_id)) return $source;
        $promo = $promo ?? \Illuminate\Support\Facades\DB::table('promo_pengujian')->where('id', $source->promo_id)->first();
        if (!$promo) return $source;
        $label = json_encode([
            'deskripsi_promo_discount' => htmlspecialchars('Diskon ' . $promo->nama_diskon, ENT_QUOTES, 'UTF-8'),
            'jumlah_promo_discount' => '',
        ]);
        if ($promo->metode === 'persentase') {
            if ((float) $source->total_discount_promo <= 0) return $source;
            $data = clone $source;
            $data->discount_promo = $label;
            return $data;
        }
        $package = in_array($promo->metode, ['paket_pengujian', 'pengujian_free_pengujian'], true);
        if ($package) {
            // Display one full package as the unit; keep persisted totals untouched.
            $data = clone $source;
            $config = $promo->konfigurasi ?? [];
            $config = is_string($config) ? json_decode($config, true) : $config;
            $rows = json_decode($source->data_pendukung_sampling);
            if (is_array($config) && $config && is_array($rows)) {
                $packagePromo = clone $promo;
                $packagePromo->konfigurasi = $config;
                $used = [];
                foreach ($rows as $row) {
                    if (empty($row->is_promo)) continue;
                    try {
                        [$unit] = QuotationPromo::rowPrice($packagePromo, $row, $row->harga_satuan, [], $used, $row->harga_total, true);
                        $row->harga_satuan = $unit;
                    } catch (\InvalidArgumentException $e) {
                        // Older quotations may no longer match the master; retain their saved price.
                    }
                }
                $data->data_pendukung_sampling = json_encode($rows);
            }
            $data->discount_promo = null;
            $data->total_discount_promo = 0;
            return $data;
        }
        if ($promo->metode !== 'free_parameter') return $source;
        $rows = json_decode($source->data_pendukung_sampling);
        if (!is_array($rows)) return $source;
        $discount = 0;
        foreach ($rows as $row) {
            // Fixed-price analysis packages do not use unit * quantity pricing.
            if (!empty($row->is_paket_analisa) || !isset($row->harga_satuan, $row->jumlah_titik, $row->harga_total)) continue;
            $gross = round((float) $row->harga_satuan * (int) $row->jumlah_titik, 2);
            $difference = round($gross - (float) $row->harga_total, 2);
            if ($difference <= 0) continue;
            $row->harga_total = $gross;
            $discount += $difference;
        }
        $discount = round($discount, 2);
        if ($discount <= 0) return $source;
        $data = clone $source;
        $data->data_pendukung_sampling = json_encode($rows);
        $data->grand_total = (float) $source->grand_total + $discount;
        $data->total_discount_promo = $discount;
        $data->discount_promo = $discount > 0 ? $label : null;
        return $data;
    }
}
