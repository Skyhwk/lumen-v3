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

    public static function contextFromPayload($payload, $modeQt = null)
    {
        $payload = is_array($payload) ? (object) $payload : $payload;
        if (!$payload) {
            return null;
        }

        $info = $payload->informasi_pelanggan ?? null;
        $info = is_array($info) ? (object) $info : $info;
        $id = $payload->id ?? ($info->id ?? null);
        $mode = $modeQt ?? ($payload->modeQt ?? ($info->modeQt ?? ($info->tipe ?? null)));
        $row = $id ? self::findQuotation($id, $mode) : null;

        return (object) [
            'id' => $row->id ?? $id,
            'promo_id' => $row ? ($row->promo_id ?? null) : ($payload->promo_id ?? null),
            'flag_status' => $row->flag_status ?? null,
            'data_lama' => $row->data_lama ?? ($payload->data_lama ?? ($info->data_lama ?? null)),
        ];
    }

    public static function contextFromRequest($request)
    {
        $id = $request->input('quotation_id') ?: $request->input('id_quotation');
        $modeQt = $request->input('mode_qt');
        $row = $id ? self::findQuotation($id, $modeQt) : null;

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
            'flag_status' => null,
            'data_lama' => $dataLama,
        ];
    }

    public static function findQuotation($id, $modeQt = null)
    {
        if (!$id) {
            return null;
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
        if ($dataLama === null || $dataLama === '' || $dataLama === 'null' || $dataLama === '[]' || $dataLama === '{}') {
            return false;
        }
        if (is_array($dataLama) || is_object($dataLama)) {
            return !empty((array) $dataLama);
        }

        $decoded = json_decode($dataLama);
        if (json_last_error() === JSON_ERROR_NONE) {
            return !empty((array) $decoded);
        }

        return trim((string) $dataLama) !== '';
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
