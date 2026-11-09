<?php

namespace App\Services\OrderBackdate;

use App\Models\LhpsAirHeader;
use App\Models\LhpsEmisiCHeader;
use App\Models\LhpsEmisiHeader;
use App\Models\LhpsEmisiIsokinetikHeader;
use App\Models\OrderHeader;
use Illuminate\Support\Collection;

/**
 * @phpstan-type LhpBundle array{kind:string,table:string,header:object,detail_class:string,custom_class:string,qr_type:string}
 */
class OrderBackdateLhpLocator
{
    /**
     * @return Collection<int, LhpBundle>
     */
    public function bundlesForOrder(OrderHeader $order): Collection
    {
        $items = collect();

        if (OrderBackdateDomainProfile::isAir($order)) {
            foreach (LhpsAirHeader::where('no_order', $order->no_order)->where('is_active', true)->get() as $header) {
                $items->push([
                    'kind' => 'air',
                    'table' => 'lhps_air_header',
                    'header' => $header,
                    'detail_class' => \App\Models\LhpsAirDetail::class,
                    'custom_class' => \App\Models\LhpsAirCustom::class,
                    'qr_type' => 'LHP',
                ]);
            }
        }

        if (OrderBackdateDomainProfile::isEmisi($order)) {
            foreach (LhpsEmisiHeader::where('no_order', $order->no_order)->where('is_active', true)->get() as $header) {
                $items->push([
                    'kind' => 'emisi_bergerak',
                    'table' => 'lhps_emisi_header',
                    'header' => $header,
                    'detail_class' => \App\Models\LhpsEmisiDetail::class,
                    'custom_class' => \App\Models\LhpsEmisiCustom::class,
                    'qr_type' => 'LHP_EMISI',
                ]);
            }

            foreach (LhpsEmisiCHeader::where('no_order', $order->no_order)->where('is_active', true)->get() as $header) {
                $items->push([
                    'kind' => 'emisi_cerobong',
                    'table' => 'lhps_emisic_header',
                    'header' => $header,
                    'detail_class' => \App\Models\LhpsEmisiCDetail::class,
                    'custom_class' => \App\Models\LhpsEmisiCCustom::class,
                    'qr_type' => 'LHP_EMISI_C',
                ]);
            }

            foreach (LhpsEmisiIsokinetikHeader::where('no_order', $order->no_order)->where('is_active', true)->get() as $header) {
                $items->push([
                    'kind' => 'emisi_isokinetik',
                    'table' => 'lhps_emisi_isokinetik_header',
                    'header' => $header,
                    'detail_class' => \App\Models\LhpsEmisiIsokinetikDetail::class,
                    'custom_class' => \App\Models\LhpsEmisiIsokinetikCustom::class,
                    'qr_type' => 'LHP_EMISI_ISOKINETIK',
                ]);
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fieldItemsForTable(
        string $table,
        OrderHeader $order,
        array $columns,
        OrderBackdateFieldResolver $resolver
    ): array {
        $items = [];
        $bundles = $this->bundlesForOrder($order)->where('table', $table);

        foreach ($bundles as $bundle) {
            $header = $bundle['header'];
            $ctx = $header->no_sampel ?? $header->no_lhp ?? $header->id;
            $items = array_merge(
                $items,
                $resolver->rowFieldsPublic($table, (int) $header->id, $columns, (string) $ctx)
            );
        }

        return $items;
    }
}
