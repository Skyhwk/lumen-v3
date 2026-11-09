<?php

namespace App\Services;

class DashboardQuotationQuery
{
    public static function excludeAutoPromo($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('kode_promo')
                ->orWhereRaw("TRIM(kode_promo) = ''")
                ->orWhereRaw("LOWER(kode_promo) NOT LIKE ?", ['%auto%']);
        });
    }
}
