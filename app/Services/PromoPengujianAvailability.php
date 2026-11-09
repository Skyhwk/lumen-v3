<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PromoPengujianAvailability
{
    public static function syncExpired($now = null): int
    {
        $today = Carbon::parse($now ?? 'now', 'Asia/Jakarta')->toDateString();

        return DB::table('promo_pengujian')
            ->where('status', 'aktif')
            ->where('masa_berlaku', 'periode')
            ->whereNotNull('berlaku_sampai')
            ->whereDate('berlaku_sampai', '<', $today)
            ->update([
                'status' => 'nonaktif',
                'updated_by' => 'system',
                'updated_at' => Carbon::now(),
            ]);
    }

    public static function isLive($promo, $now = null): bool
    {
        if (!$promo || ($promo->status ?? null) !== 'aktif') {
            return false;
        }

        if (($promo->masa_berlaku ?? null) !== 'periode') {
            return true;
        }

        $today = Carbon::parse($now ?? 'now', 'Asia/Jakarta')->toDateString();
        $dari = self::dateOrNull($promo->berlaku_dari ?? null);
        $sampai = self::dateOrNull($promo->berlaku_sampai ?? null);

        if ($dari && $today < $dari) {
            return false;
        }
        if ($sampai && $today > $sampai) {
            return false;
        }

        return true;
    }

    public static function canUse($promo, $quotation = null, $now = null): bool
    {
        if (!$promo) {
            return false;
        }

        return self::isLive($promo, $now) || self::isGrandfathered($promo->id ?? null, $quotation);
    }

    public static function isGrandfathered($promoId, $quotation): bool
    {
        if (!$promoId || !$quotation) {
            return false;
        }

        $usedPromoId = $quotation->promo_id ?? null;
        if ((int) $usedPromoId !== (int) $promoId) {
            return false;
        }

        $flag = strtolower(trim((string) ($quotation->flag_status ?? '')));
        if ($flag === 'ordered') {
            return true;
        }

        return self::hasDataLama($quotation->data_lama ?? null);
    }

    public static function contextFromPayload($payload, $modeQt = null, $source = null)
    {
        $payload = is_array($payload) ? (object) $payload : $payload;
        if (!$payload) {
            return null;
        }

        $info = $payload->informasi_pelanggan ?? null;
        $info = is_array($info) ? (object) $info : $info;
        $id = $payload->id ?? ($info->id ?? null);
        $mode = $modeQt ?? ($payload->modeQt ?? ($info->modeQt ?? ($info->tipe ?? null)));
        $source = $source ?? ($payload->source ?? ($info->source ?? null));
        $row = $id ? self::findQuotation($id, $mode, $source) : null;

        return (object) [
            'id' => $row->id ?? $id,
            'promo_id' => $row ? ($row->promo_id ?? null) : ($payload->promo_id ?? null),
            'flag_status' => $row->flag_status ?? ($payload->flag_status ?? null),
            'data_lama' => $row->data_lama ?? ($payload->data_lama ?? ($info->data_lama ?? null)),
        ];
    }

    public static function contextFromRequest($request)
    {
        $id = $request->input('quotation_id') ?: $request->input('id_quotation');
        $modeQt = $request->input('mode_qt');
        $source = $request->input('source');
        $row = $id ? self::findQuotation($id, $modeQt, $source) : null;

        if ($row) {
            return $row;
        }

        $currentPromoId = $request->input('current_promo_id');
        $flagStatus = $request->input('flag_status');
        $dataLama = $request->input('data_lama');
        if (!$currentPromoId && !$flagStatus && !self::hasDataLama($dataLama)) {
            return null;
        }

        return (object) [
            'id' => $id,
            'promo_id' => $currentPromoId,
            'flag_status' => $flagStatus,
            'data_lama' => $dataLama,
        ];
    }

    public static function findQuotation($id, $modeQt = null, $source = null)
    {
        if (!$id) {
            return null;
        }

        $source = strtolower((string) $source);
        if ($source === 'request_qr') {
            return DB::table('request_qr')->where('id', $id)->first();
        }

        $tables = [];
        $mode = strtolower((string) $modeQt);
        if ($mode === 'kontrak') {
            $tables = ['request_quotation_kontrak_H'];
        } elseif (in_array($mode, ['non_kontrak', 'nonkontrak'], true)) {
            $tables = ['request_quotation'];
        } else {
            $tables = ['request_quotation', 'request_quotation_kontrak_H'];
        }

        foreach ($tables as $table) {
            $row = DB::table($table)->where('id', $id)->first();
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function hasDataLama($dataLama): bool
    {
        $decoded = self::decodeDataLama($dataLama);
        if ($decoded === null) {
            return false;
        }

        if (self::isList($decoded)) {
            foreach ($decoded as $item) {
                if (self::dataLamaHasOrder($item)) {
                    return true;
                }
            }

            return false;
        }

        return self::dataLamaHasOrder($decoded);
    }

    private static function decodeDataLama($dataLama)
    {
        if ($dataLama === null || $dataLama === '' || $dataLama === 'null' || $dataLama === '[]' || $dataLama === '{}') {
            return null;
        }

        if (is_array($dataLama) || is_object($dataLama)) {
            return $dataLama;
        }

        $decoded = json_decode($dataLama);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            return null;
        }

        return $decoded;
    }

    private static function dataLamaHasOrder($item): bool
    {
        if ($item === null || (!is_array($item) && !is_object($item))) {
            return false;
        }

        $row = (array) $item;
        if (isset($row['data_lama'])) {
            return self::hasDataLama($row['data_lama']);
        }

        $idOrder = $row['id_order'] ?? ($row['id_order_header'] ?? null);
        $noOrder = $row['no_order'] ?? null;

        return self::filledOrderValue($idOrder) && self::filledOrderValue($noOrder);
    }

    private static function filledOrderValue($value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        $text = trim((string) $value);

        return $text !== '' && strtolower($text) !== 'null' && $text !== '0';
    }

    private static function isList($value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }

    private static function dateOrNull($value)
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }
}
