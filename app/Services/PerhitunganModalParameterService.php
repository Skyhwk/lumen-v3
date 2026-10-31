<?php

namespace App\Services;

use App\Models\PerhitunganModalParameter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PerhitunganModalParameterService
{
    public static function effectiveDateExpression(string $alias = 'mpmp'): string
    {
        return "COALESCE({$alias}.tanggal_berlaku, DATE({$alias}.created_at), '1970-01-01')";
    }

    public static function getTotalModalEfektif(int $idParameter, ?string $date = null): float
    {
        $date = $date ?: Carbon::today()->toDateString();
        $effectiveDate = self::effectiveDateExpression('master_perhitungan_modal_parameter');

        $row = PerhitunganModalParameter::query()
            ->where('id_parameter', $idParameter)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$date])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first(['total_modal']);

        return $row ? (float) $row->total_modal : 0.0;
    }

    public static function getVersiEfektif(int $idParameter, ?string $date = null): ?PerhitunganModalParameter
    {
        $date = $date ?: Carbon::today()->toDateString();
        $effectiveDate = self::effectiveDateExpression('master_perhitungan_modal_parameter');

        return PerhitunganModalParameter::query()
            ->where('id_parameter', $idParameter)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("{$effectiveDate} <= ?", [$date])
            ->orderByRaw("{$effectiveDate} DESC")
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Modal efektif per tanggal acuan (mis. tanggal berlaku harga parameter).
     *
     * @return array{total_modal: float, tanggal_berlaku: ?string, komponen: array<int, array{label: string, harga: int}>}
     */
    public static function getDetailEfektif(int $idParameter, ?string $date = null): array
    {
        $versi = self::getVersiEfektif($idParameter, $date);

        if (!$versi) {
            return [
                'total_modal' => 0.0,
                'tanggal_berlaku' => null,
                'komponen' => [],
            ];
        }

        $komponen = DB::table('master_perhitungan_modal_parameter_komponen')
            ->where('id_perhitungan_modal', $versi->id)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['label', 'harga'])
            ->map(function ($row) {
                return [
                    'label' => (string) $row->label,
                    'harga' => (int) $row->harga,
                ];
            })
            ->values()
            ->all();

        return [
            'total_modal' => (float) $versi->total_modal,
            'tanggal_berlaku' => $versi->tanggal_berlaku
                ? Carbon::parse($versi->tanggal_berlaku)->format('Y-m-d')
                : null,
            'komponen' => $komponen,
        ];
    }

    public static function parseHarga($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) preg_replace('/[^0-9]/', '', (string) $value);
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{label: string, harga: int}>
     */
    public static function normalizeKomponenInput($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $items[] = [
                'label' => $label,
                'harga' => self::parseHarga($row['harga'] ?? 0),
            ];
        }

        return $items;
    }

    public static function fingerprintKomponen(array $komponen): string
    {
        $normalized = array_map(function ($row) {
            return [
                'label' => mb_strtolower(trim((string) ($row['label'] ?? ''))),
                'harga' => (int) ($row['harga'] ?? 0),
            ];
        }, $komponen);

        usort($normalized, function ($a, $b) {
            return [$a['label'], $a['harga']] <=> [$b['label'], $b['harga']];
        });

        return json_encode($normalized);
    }

    public static function sumKomponen(array $komponen): int
    {
        return array_reduce($komponen, function ($carry, $row) {
            return $carry + (int) ($row['harga'] ?? 0);
        }, 0);
    }

    public static function persistKomponen(int $headerId, array $komponen): void
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        foreach ($komponen as $index => $row) {
            DB::table('master_perhitungan_modal_parameter_komponen')->insert([
                'id_perhitungan_modal' => $headerId,
                'label' => $row['label'],
                'harga' => $row['harga'],
                'urutan' => $index + 1,
                'created_at' => $now,
            ]);
        }
    }
}
