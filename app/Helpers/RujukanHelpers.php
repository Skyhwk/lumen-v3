<?php

namespace App\Helpers;

class RujukanHelpers
{
    public static function formatHasilUjiValue($value): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '' || $raw === 'null') {
            return '-';
        }

        $normalized = str_replace(',', '.', $raw);
        if (!is_numeric($normalized)) {
            return $raw;
        }

        $num = (float) $normalized;
        if (!is_finite($num)) {
            return $raw;
        }

        if (str_contains($normalized, '.')) {
            return self::trimTrailingZeros($normalized);
        }

        return (string) $num;
    }

    public static function formatRujukanDisplay($value): string
    {
        if ($value === null || $value === '' || $value === 'null') {
            return '-';
        }

        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return '-';
        }

        if (preg_match('/^[\d.,]+$/', $text)) {
            return self::formatHasilUjiValue($text);
        }

        return $text;
    }

    public static function isMelebihiRujukan($hasilRaw, $rujukanRaw): bool
    {
        $hasil = self::parseHasilUjiNumeric($hasilRaw);
        if ($hasil === null) {
            return false;
        }

        $rule = self::parseRujukanRule($rujukanRaw);
        if ($rule === null) {
            return false;
        }

        switch ($rule['type']) {
            case 'max':
                return $hasil > $rule['limit'];
            case 'range':
                return $hasil > $rule['max'];
            case 'operator':
                $operator = $rule['operator'];
                $limit = $rule['limit'];
                if (in_array($operator, ['<', '≤', '<=', '=<'], true)) {
                    return $hasil >= $limit;
                }
                return false;
            default:
                return false;
        }
    }

    private static function parseHasilUjiNumeric($value): ?float
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '' || $text === '-' || $text === 'null') {
            return null;
        }

        $num = (float) str_replace(',', '.', $text);
        return is_finite($num) ? $num : null;
    }

    private static function parseRujukanNumber($value): ?float
    {
        $text = trim(str_replace(',', '.', (string) ($value ?? '')));
        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        $num = (float) $text;
        return is_finite($num) ? $num : null;
    }

    private static function parseRujukanRule($rujukanRaw): ?array
    {
        $text = trim((string) ($rujukanRaw ?? ''));
        if ($text === '' || $text === '-' || $text === 'null') {
            return null;
        }

        $normalized = str_replace(',', '.', $text);

        if (preg_match('/^([\d.]+)\s*(?:-|–|—|s\/d|sd|to)\s*([\d.]+)$/i', $normalized, $rangeMatch)) {
            $min = self::parseRujukanNumber($rangeMatch[1]);
            $max = self::parseRujukanNumber($rangeMatch[2]);
            if ($min !== null && $max !== null) {
                return [
                    'type' => 'range',
                    'min' => min($min, $max),
                    'max' => max($min, $max),
                ];
            }
        }

        if (preg_match('/^([<≤>≥]=?|=<=?)\s*([\d.]+)$/', $normalized, $operatorMatch)) {
            $limit = self::parseRujukanNumber($operatorMatch[2]);
            if ($limit !== null) {
                return [
                    'type' => 'operator',
                    'operator' => $operatorMatch[1],
                    'limit' => $limit,
                ];
            }
        }

        if (preg_match('/(?:maks(?:imum)?|max\.?)\s*([\d.]+)/i', $normalized, $maxLabelMatch)) {
            $limit = self::parseRujukanNumber($maxLabelMatch[1]);
            if ($limit !== null) {
                return ['type' => 'max', 'limit' => $limit];
            }
        }

        if (preg_match('/^[\d.]+$/', $normalized)) {
            $limit = self::parseRujukanNumber($normalized);
            if ($limit !== null) {
                return ['type' => 'max', 'limit' => $limit];
            }
        }

        return null;
    }

    private static function trimTrailingZeros(string $value): string
    {
        $value = preg_replace('/(\.\d*?[1-9])0+$/', '$1', $value);
        return preg_replace('/\.0+$/', '', $value);
    }
}
