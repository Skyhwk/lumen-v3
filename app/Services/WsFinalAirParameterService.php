<?php

namespace App\Services;

use App\Models\OrderDetail;
use App\Models\SampelDiantar;
use Illuminate\Support\Collection;

class WsFinalAirParameterService
{
    /** @return string[] */
    public static function debitAirAliasNames(): array
    {
        return [
            'Debit Air',
            'Debit Air (m3/ton)',
            'Debit Air (m3/Hari)',
            'Debit Air (L/Orang/Hari)',
            'Debit Air (L/kg)',
            'Debit Air (L/L)',
            'Debit Air (m3/L)',
            'Debit Air (L/hari)',
            'Debit Air (m3/dtk)',
            'Debit Air (L/dtk)',
            'Debit Air (L/jam)',
        ];
    }

    public static function hasFieldValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        $trimmed = is_string($value) ? trim($value) : $value;

        return $trimmed !== '' && $trimmed !== '-';
    }

    public static function parseOrderParameters(?string $parameterJson): Collection
    {
        if ($parameterJson === null || $parameterJson === '') {
            return collect();
        }

        $decoded = json_decode(str_replace('&quot;', '"', $parameterJson), true);
        if (!is_array($decoded)) {
            return collect();
        }

        return collect($decoded)->map(function ($entry) {
            $parts = explode(';', (string) $entry);
            $id = isset($parts[0]) && $parts[0] !== '' ? (int) $parts[0] : null;
            $value = isset($parts[1]) ? trim($parts[1]) : trim((string) $entry);

            return [
                'id' => $id,
                'value' => $value,
            ];
        })->filter(fn ($row) => $row['value'] !== '')->values();
    }

    /** @return array<int, array<string, mixed>>|null */
    public static function decodeInternalDataJson(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $normalized = str_replace('&quot;', '"', $raw);
        $decoded = json_decode(html_entity_decode($normalized), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Cari baris internal_data untuk no_sampel — scan semua header & detail (sama ModalEvaluation.js).
     */
    public static function findSdInternalRowForSample(string $noOrder, string $noSampel): ?array
    {
        $headers = SampelDiantar::with('detail')
            ->where('no_order', $noOrder)
            ->get();

        foreach ($headers as $header) {
            foreach ($header->detail as $detail) {
                $decoded = self::decodeInternalDataJson($detail->internal_data);
                if (!$decoded) {
                    continue;
                }

                foreach ($decoded as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if ((string) ($row['no_sampel'] ?? '') !== $noSampel) {
                        continue;
                    }
                    if (array_key_exists('is_active', $row) && $row['is_active'] === false) {
                        continue;
                    }

                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Samakan dengan ModalEvaluation.js (SD / sampel diantar).
     */
    public static function resolveSdParsedInternalData(OrderDetail $orderDetail): ?array
    {
        if ($orderDetail->kategori_1 !== 'SD') {
            return null;
        }

        return self::findSdInternalRowForSample(
            (string) $orderDetail->no_order,
            (string) $orderDetail->no_sampel
        );
    }

    /**
     * @param  string[]  $labParameters  Nama parameter dari baris evaluasi (lab)
     * @return string[]
     */
    public static function buildParametersDiUji(array $labParameters, OrderDetail $orderDetail, ?array $sdParsed = null): array
    {
        $parametersDiUji = collect($labParameters)
            ->filter(fn ($p) => $p !== null && $p !== '')
            ->values()
            ->all();

        $isSd = $orderDetail->kategori_1 === 'SD';
        $sdParsed = $sdParsed ?? self::resolveSdParsedInternalData($orderDetail);
        $lapangan = $orderDetail->dataLapanganAir;

        $ph = $isSd ? ($sdParsed['ph'] ?? null) : ($lapangan->ph ?? null);
        if (self::hasFieldValue($ph)) {
            $parametersDiUji[] = 'pH';
            $parametersDiUji[] = 'pH (NA)';
        }

        $suhu = $isSd ? ($sdParsed['suhu'] ?? null) : ($lapangan->suhu_air ?? null);
        if (self::hasFieldValue($suhu)) {
            $parametersDiUji[] = 'Suhu';
            $parametersDiUji[] = 'Suhu (NA)';
        }

        $sampah = $isSd ? ($sdParsed['sampah'] ?? null) : ($lapangan->sampah ?? null);
        if (self::hasFieldValue($sampah)) {
            $parametersDiUji[] = 'Sampah';
        }

        $debit = $isSd
            ? ($sdParsed['debit_air'] ?? $sdParsed['debit'] ?? null)
            : ($lapangan->debit_air ?? null);

        if (self::hasFieldValue($debit)) {
            $parametersDiUji = array_merge($parametersDiUji, self::debitAirAliasNames());
        }

        return array_values(array_unique($parametersDiUji));
    }

    /**
     * @param  string[]  $parametersDiUji
     */
    public static function filterBelumDiuji(Collection $paramAll, array $parametersDiUji): Collection
    {
        return $paramAll
            ->filter(fn ($row) => !in_array($row['value'], $parametersDiUji, true))
            ->values();
    }

    /**
     * @param  string[]  $parametersDiUji
     * @return array{total:int,tested:int,belum_diuji:Collection,parameters_di_uji:array}
     */
    public static function summarize(Collection $paramAll, array $parametersDiUji): array
    {
        $belumDiuji = self::filterBelumDiuji($paramAll, $parametersDiUji);
        $total = $paramAll->count();
        $tested = $total - $belumDiuji->count();

        return [
            'total' => $total,
            'tested' => $tested,
            'belum_diuji' => $belumDiuji,
            'parameters_di_uji' => $parametersDiUji,
        ];
    }
}
