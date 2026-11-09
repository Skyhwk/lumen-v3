<?php

namespace App\Services\OrderBackdate;

use App\Models\OrderHeader;

class OrderBackdateDomainProfile
{
    public static function normalizeSampling(OrderHeader $order): string
    {
        return strtoupper(trim((string) $order->status_sampling));
    }

    public static function isAir(OrderHeader $order): bool
    {
        $sampling = self::normalizeSampling($order);
        $sub = strtolower((string) ($order->sub_kategori ?? ''));

        if ($sampling === 'AIR' || $sampling === '') {
            return true;
        }

        return strpos($sub, 'air') !== false;
    }

    public static function isEmisi(OrderHeader $order): bool
    {
        $sampling = self::normalizeSampling($order);
        $sub = strtolower((string) ($order->sub_kategori ?? ''));

        if ($sampling === 'EMISI' || strpos($sampling, 'EMISI') !== false) {
            return true;
        }

        return strpos($sub, 'emisi') !== false;
    }
}
