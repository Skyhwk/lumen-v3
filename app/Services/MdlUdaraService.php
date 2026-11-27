<?php

namespace App\Services;

use App\Models\MdlUdara;
use Illuminate\Support\Collection;

class MdlUdaraService
{
    public static function activeRowForParameter($parameterId): ?MdlUdara
    {
        if ($parameterId === null || $parameterId === '') {
            return null;
        }

        return MdlUdara::where('parameter_id', $parameterId)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }

  /**
     * @param  array<int|string>|Collection<int|string, mixed>  $parameterIds
     */
    public static function loadForParameterIds($parameterIds): Collection
    {
        $ids = collect($parameterIds)->filter(fn ($id) => $id !== null && $id !== '')->unique()->values()->all();
        if (!$ids) {
            return collect();
        }

        return MdlUdara::whereIn('parameter_id', $ids)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @return mixed
     */
    public static function limitForColumn(?MdlUdara $row, string $column)
    {
        if (!$row) {
            return null;
        }

        $value = $row->{$column} ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * @return mixed
     */
    public static function limitForWsIndex(?MdlUdara $row, int $index)
    {
        return self::limitForColumn($row, 'hasil' . ($index ?: 1));
    }

    /**
     * Hasil numerik di bawah MDL dikembalikan sebagai "&lt;{mdl}".
     *
     * @return mixed
     */
    public static function formatBelowMdl($value, $mdlValue)
    {
        if ($value === null || $value === '' || $value === '-' || $value === '##') {
            return $value;
        }

        if (is_string($value) && str_contains($value, '<')) {
            return $value;
        }

        if ($mdlValue === null || $mdlValue === '') {
            return $value;
        }

        if (!is_numeric($value) || !is_numeric($mdlValue)) {
            return $value;
        }

        if ((float) $value < (float) $mdlValue) {
            return '<' . $mdlValue;
        }

        return $value;
    }

    /**
     * @return mixed
     */
    public static function applyColumn($value, ?MdlUdara $row, string $column)
    {
        return self::formatBelowMdl($value, self::limitForColumn($row, $column));
    }

    /**
     * @return mixed
     */
    public static function applyWsIndex($value, ?MdlUdara $row, int $index)
    {
        return self::formatBelowMdl($value, self::limitForWsIndex($row, $index));
    }

    /**
     * Dipakai di draft / WS Final saat koleksi MDL sudah di-load per parameter order.
     */
    /**
     * @return mixed
     */
    public static function formatHasilUjiForDisplay($hasilUji, int $index, $parameterId, Collection $mdlUdaraRows)
    {
        if ($hasilUji === null || $hasilUji === '' || $hasilUji === '-' || $hasilUji === '##') {
            return $hasilUji;
        }

        $hasilUji = (string) $hasilUji;
        if (str_contains($hasilUji, '<')) {
            return $hasilUji;
        }

        if (!$parameterId) {
            return $hasilUji;
        }

        $column = 'hasil' . ($index ?: 1);
        $row = $mdlUdaraRows->first(function ($item) use ($parameterId, $column) {
            return (string) $item->parameter_id === (string) $parameterId
                && $item->{$column} !== null
                && $item->{$column} !== '';
        });

        return self::formatBelowMdl($hasilUji, $row ? $row->{$column} : null);
    }
}