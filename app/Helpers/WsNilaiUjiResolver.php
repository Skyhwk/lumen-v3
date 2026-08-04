<?php

namespace App\Helpers;

use App\Models\TemplateSatuan;

class WsNilaiUjiResolver
{
    public static function satuanIndexMap(): array
    {
        return TemplateSatuan::where('kategori', 'udara')
            ->where('is_active', true)
            ->pluck('column_number', 'satuan')
            ->all();
    }

    public static function buildMdlLimitMap(iterable $mdlRows): array
    {
        $map = [];
        foreach ($mdlRows as $row) {
            for ($i = 1; $i <= config('column_ws.ws_value_udara.max'); $i++) {
                $column = "hasil{$i}";
                if ($row->$column !== null && $row->$column !== '') {
                    $map[$row->parameter_id][$i] = (float) $row->$column;
                }
            }
        }

        return $map;
    }

    public static function resolve($item, array $satuanMap, array $mdlLimitMap): string
    {
        $source = $item->ws_udara ?? ($item->ws_value_linkungan ?? null);
        if (!$source) {
            return 'noWs';
        }

        $hasil = is_array($source) ? $source : $source->toArray();
        $satuan = $item->satuan ?? null;
        $index = ($satuan !== null && isset($satuanMap[$satuan])) ? (int) $satuanMap[$satuan] : null;
        $raw = self::extractRawValue($hasil, $index);

        if ($raw === null || $raw === '') {
            return '-';
        }

        return self::applyMdlLimit($index, $item->id_parameter, $raw, $mdlLimitMap);
    }

    private static function extractRawValue(array $hasil, ?int $index): ?string
    {
        $has = static fn (string $key): bool => isset($hasil[$key]) && $hasil[$key] !== null && $hasil[$key] !== '';

        if ($index === null) {
            if ($has('f_koreksi_c')) {
                return (string) $hasil['f_koreksi_c'];
            }

            for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
                $key = "f_koreksi_c{$i}";
                if ($has($key)) {
                    return (string) $hasil[$key];
                }
            }

            if ($has('C')) {
                return (string) $hasil['C'];
            }

            for ($i = config('column_ws.ws_value_lingkungan.min'); $i <= config('column_ws.ws_value_lingkungan.max'); $i++) {
                $key = "C{$i}";
                if ($has($key)) {
                    return (string) $hasil[$key];
                }
            }

            for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
                $key = "f_koreksi_{$i}";
                if ($has($key)) {
                    return (string) $hasil[$key];
                }
            }

            for ($i = config('column_ws.ws_value_udara.min'); $i <= config('column_ws.ws_value_udara.max'); $i++) {
                $key = "hasil{$i}";
                if ($has($key)) {
                    return (string) $hasil[$key];
                }
            }

            return null;
        }

        $keysToTry = ["f_koreksi_{$index}", "hasil{$index}", "f_koreksi_c{$index}", "C{$index}"];
        if ($index === 17) {
            $fallbackKeys = ['f_koreksi_c2', 'C2', 'f_koreksi_2', 'hasil2'];
        } elseif ($index === 15) {
            $fallbackKeys = ['f_koreksi_c3', 'C3', 'f_koreksi_3', 'hasil3'];
        } elseif ($index === 16) {
            $fallbackKeys = ['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'];
        } else {
            $fallbackKeys = ['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'];
        }

        foreach (array_merge($keysToTry, $fallbackKeys) as $key) {
            if ($has($key)) {
                return (string) $hasil[$key];
            }
        }

        return null;
    }

    private static function applyMdlLimit(?int $index, $parameterId, string $hasilUji, array $mdlLimitMap): string
    {
        if (!$hasilUji || $hasilUji === '-' || strpos($hasilUji, '<') !== false || !$parameterId) {
            return $hasilUji;
        }

        $colIndex = $index ?: 1;
        $limit = $mdlLimitMap[$parameterId][$colIndex] ?? null;
        if ($limit !== null && $limit > (float) $hasilUji) {
            return '<' . $limit;
        }

        return $hasilUji;
    }
}
