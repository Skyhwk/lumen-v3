<?php

namespace App\Helpers;

use App\Models\HargaParameter;
use App\Models\KonfigurasiLabelParameter;
use Illuminate\Support\Collection;

class ParameterLabelConfigHelper
{
    /**
     * Cache internal request agar tidak melakukan query berulang per parameter ID.
     * @var array<int, KonfigurasiLabelParameter|null>
     */
    protected static $cache = [];

    /**
     * Cek apakah kategori adalah Air (id_kategori == 1).
     */
    public static function isKategoriAir($kategori): bool
    {
        if (is_numeric($kategori)) {
            return (int) $kategori === 1;
        }

        if (is_string($kategori)) {
            $parts = explode('-', $kategori);
            if (isset($parts[0]) && trim($parts[0]) === '1') {
                return true;
            }
            if (isset($parts[1]) && strtolower(trim($parts[1])) === 'air') {
                return true;
            }
            return strtolower(trim($kategori)) === 'air';
        }

        return false;
    }

    /**
     * Mengambil map KonfigurasiLabelParameter berdasarkan array id_parameter (khusus kategori air).
     *
     * @param array<int|string> $parameterIds
     * @return Collection<int, KonfigurasiLabelParameter>
     */
    public static function getLabelConfigs(array $parameterIds): Collection
    {
        $ids = array_filter(array_map('intval', $parameterIds));
        if (empty($ids)) {
            return collect();
        }

        return KonfigurasiLabelParameter::where('is_active', true)
            ->whereIn('id_parameter', $ids)
            ->get()
            ->keyBy('id_parameter');
    }

    /**
     * Memperkaya param_map (yang sebelumnya diambil dari HargaParameter) dengan data dari KonfigurasiLabelParameter.
     * Memprioritaskan KonfigurasiLabelParameter dengan fallback ke HargaParameter.
     *
     * @param array &$paramMap
     * @param array $parameterIds
     * @param mixed $kategori
     */
    public static function enrichParamMap(array &$paramMap, array $parameterIds, $kategori = null): void
    {
        if ($kategori !== null && !self::isKategoriAir($kategori)) {
            return;
        }

        // Normalisasi format parameterIds (bisa array integer id atau array string 'id;nama')
        $cleanIds = [];
        foreach ($parameterIds as $item) {
            if (is_numeric($item)) {
                $cleanIds[] = (int) $item;
            } elseif (is_string($item) && strpos($item, ';') !== false) {
                $cleanIds[] = (int) explode(';', $item)[0];
            }
        }

        $configs = self::getLabelConfigs($cleanIds);
        if ($configs->isEmpty()) {
            return;
        }

        foreach ($configs as $idParam => $cfg) {
            $paramName = $cfg->nama_parameter;

            // Update jika sudah ada di paramMap
            if (isset($paramMap[$paramName])) {
                $target = $paramMap[$paramName];
                if (!empty($cfg->regen)) {
                    $target->regen = $cfg->regen;
                }
                if ($cfg->volume !== null && $cfg->volume !== '') {
                    $target->volume = $cfg->volume;
                }
            } else {
                // Tambahkan entri baru jika belum ada
                $paramMap[$paramName] = (object) [
                    'id_parameter' => $cfg->id_parameter,
                    'nama_parameter' => $cfg->nama_parameter,
                    'volume' => $cfg->volume,
                    'regen' => $cfg->regen,
                ];
            }
        }
    }

    /**
     * Mendapatkan volume parameter dengan prioritas KonfigurasiLabelParameter lalu fallback ke nilai asal.
     */
    public static function resolveVolume($idParam, $kategori, $fallbackVolume = 0): float
    {
        if ($idParam && self::isKategoriAir($kategori)) {
            $id = (int) $idParam;
            if (!array_key_exists($id, self::$cache)) {
                self::$cache[$id] = KonfigurasiLabelParameter::where('id_parameter', $id)
                    ->where('is_active', true)
                    ->first();
            }

            $cfg = self::$cache[$id];
            if ($cfg && $cfg->volume !== null && $cfg->volume !== '' && $cfg->volume !== '-') {
                return (float) $cfg->volume;
            }
        }

        return ($fallbackVolume !== null && $fallbackVolume !== '' && $fallbackVolume !== '-')
            ? (float) $fallbackVolume
            : 0.0;
    }

    /**
     * Mendapatkan regen parameter dengan prioritas KonfigurasiLabelParameter lalu fallback ke nilai asal.
     */
    public static function resolveRegen($idParam, $kategori, $fallbackRegen = 'ORI'): string
    {
        if ($idParam && self::isKategoriAir($kategori)) {
            $id = (int) $idParam;
            if (!array_key_exists($id, self::$cache)) {
                self::$cache[$id] = KonfigurasiLabelParameter::where('id_parameter', $id)
                    ->where('is_active', true)
                    ->first();
            }

            $cfg = self::$cache[$id];
            if ($cfg && !empty($cfg->regen) && $cfg->regen !== '-') {
                return $cfg->regen;
            }
        }

        return (!empty($fallbackRegen) && $fallbackRegen !== '-') ? $fallbackRegen : 'ORI';
    }
}
