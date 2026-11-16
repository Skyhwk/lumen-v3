<?php

namespace App\Services\QuotationGenerate;

class QuotationGenerateCampaign
{
    public static function apply(string $key): array
    {
        $campaign = config('quotation_auto.campaigns.' . $key);
        if (!is_array($campaign) || $campaign === []) {
            throw new \RuntimeException('Campaign quotation_auto.' . $key . ' tidak terdaftar.');
        }

        foreach ($campaign as $field => $value) {
            config(['quotation_auto.generate.' . $field => $value]);
        }

        return $campaign;
    }
}
