<?php

namespace App\Support;

class FdlLingkunganSharedParameters
{
    /**
     * Parameter yang diisi lewat form header / jalur store ambient, bukan multiselect apps-fdl.
     */
    public static function excludedFromMultiselect(): array
    {
        return [
            'Kelembaban',
            'Suhu',
            'Laju Ventilasi',
            'Laju Ventilasi (8 Jam)',
            'Tekanan Udara (LK)',
            'Tekanan Udara',
            'Kecepatan Angin',
            'Kelembapan (24 Jam)',
            'Suhu (24 Jam)',
        ];
    }

    /**
     * Whitelist parameter ambient pada branch store saat param[] kosong (LH & LK mobile).
     */
    public static function ambientStoreParameters(): array
    {
        return [
            'Kelembaban',
            'Suhu',
            'Laju Ventilasi',
            'Laju Ventilasi (8 Jam)',
            'Tekanan Udara (LK)',
            'Kelembapan (24 Jam)',
            'Suhu (24 Jam)',
        ];
    }

    /** @param mixed $orderParameterJson */
    public static function parseOrderParameterLabels($orderParameterJson): array
    {
        if (is_string($orderParameterJson)) {
            $raw = json_decode($orderParameterJson, true);
        } else {
            $raw = $orderParameterJson;
        }

        if (!is_array($raw)) {
            return [];
        }

        $labels = [];
        foreach ($raw as $item) {
            if (is_string($item) && str_contains($item, ';')) {
                $parts = explode(';', $item);
                $labels[] = isset($parts[1]) ? trim($parts[1]) : trim($item);
            } elseif (is_string($item)) {
                $labels[] = trim($item);
            }
        }

        return array_values(array_unique(array_filter($labels)));
    }

    public static function getReadingThreshold(string $parameterName): int
    {
        $kateg = self::resolveKategoriFromParameterName($parameterName);
        if ($kateg === null) {
            return 1;
        }

        $lower = strtolower($parameterName);
        if ($kateg === '24 Jam') {
            return (str_contains($lower, 'pm') || str_contains($lower, 'tsp')) ? 25 : 4;
        }
        if ($kateg === '8 Jam') {
            return (str_contains($lower, 'pm') || str_contains($lower, 'tsp')) ? 8 : 3;
        }
        if ($kateg === '6 Jam') {
            return 6;
        }
        if ($kateg === '3 Jam') {
            return 3;
        }

        return 1;
    }

    public static function applyParameterNameScope($query, string $orderParameterLabel): void
    {
        $query->where(function ($q) use ($orderParameterLabel) {
            $q->where('parameter', $orderParameterLabel);
            foreach (self::ambientStoreParameters() as $canonical) {
                if (self::orderParameterMatchesCanonical($orderParameterLabel, $canonical)) {
                    $q->orWhere('parameter', $canonical);
                }
            }
        });
    }

    public static function countDetailReadings(string $noSampel, string $orderParameterLabel, string $modelClass): int
    {
        $rows = self::fetchDetailRows($noSampel, $modelClass);

        return self::countReadingsInRows($rows, $orderParameterLabel);
    }

    public static function detailExistsForShift(string $noSampel, string $orderParameterLabel, string $shift, string $modelClass): bool
    {
        $rows = self::fetchDetailRows($noSampel, $modelClass);

        return self::detailExistsForShiftInRows($rows, $orderParameterLabel, $shift);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object{parameter: string, shift_pengambilan: string, kategori_pengujian: string}>
     */
    public static function fetchDetailRows(string $noSampel, string $modelClass)
    {
        $no = strtoupper(trim($noSampel));

        return $modelClass::where('no_sampel', $no)
            ->get(['parameter', 'shift_pengambilan', 'kategori_pengujian']);
    }

    public static function storedParameterMatchesOrderLabel(string $storedParameter, string $orderParameterLabel): bool
    {
        if ($storedParameter === $orderParameterLabel) {
            return true;
        }

        if (self::normalizeParameterLabel($storedParameter) === self::normalizeParameterLabel($orderParameterLabel)) {
            return true;
        }

        foreach (self::ambientStoreParameters() as $canonical) {
            if (
                self::orderParameterMatchesCanonical($orderParameterLabel, $canonical)
                && self::orderParameterMatchesCanonical($storedParameter, $canonical)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $detailRows
     */
    public static function countReadingsInRows($detailRows, string $orderParameterLabel): int
    {
        $count = 0;
        foreach ($detailRows as $row) {
            if (self::storedParameterMatchesOrderLabel($row->parameter, $orderParameterLabel)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param \Illuminate\Support\Collection<int, object> $detailRows
     */
    public static function detailExistsForShiftInRows($detailRows, string $orderParameterLabel, string $shift): bool
    {
        $kateg = self::resolveKategoriFromParameterName($orderParameterLabel);
        $shiftFields = ($kateg !== null)
            ? self::resolveShiftFieldsForStore($kateg, $shift)
            : null;

        foreach ($detailRows as $row) {
            if (!self::storedParameterMatchesOrderLabel($row->parameter, $orderParameterLabel)) {
                continue;
            }

            if ($kateg !== null && $shiftFields !== null) {
                if (
                    $row->kategori_pengujian === $shiftFields['kategori_pengujian']
                    && $row->shift_pengambilan === $shiftFields['shift_pengambilan']
                ) {
                    return true;
                }
                continue;
            }

            if ($shift !== 'L1') {
                if ($row->shift_pengambilan === $shift) {
                    return true;
                }
                continue;
            }

            if (in_array($row->shift_pengambilan, ['Sesaat', 'L1'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parameter order yang masih perlu diinput pada shift tertentu.
     *
     * @param array<int, string> $orderParameterLabels
     * @return array<int, string>
     */
    /**
     * @param \Illuminate\Support\Collection<int, object>|null $detailRows
     */
    public static function buildPendingParametersForShift(
        string $noSampel,
        string $shift,
        array $orderParameterLabels,
        string $modelClass,
        $detailRows = null
    ): array {
        if ($detailRows === null) {
            $detailRows = self::fetchDetailRows($noSampel, $modelClass);
        }

        $pending = [];

        foreach (array_unique($orderParameterLabels) as $orderParam) {
            if ($orderParam === '' || $orderParam === null) {
                continue;
            }

            $kateg = self::resolveKategoriFromParameterName($orderParam);

            if ($kateg === null) {
                if ($shift !== 'L1') {
                    continue;
                }
                if (self::detailExistsForShiftInRows($detailRows, $orderParam, $shift)) {
                    continue;
                }
                $pending[] = $orderParam;
                continue;
            }

            if (self::countReadingsInRows($detailRows, $orderParam) >= self::getReadingThreshold($orderParam)) {
                continue;
            }
            if (self::detailExistsForShiftInRows($detailRows, $orderParam, $shift)) {
                continue;
            }

            $pending[] = $orderParam;
        }

        return array_values(array_unique($pending));
    }

    /**
     * @param array<int, string> $orderParameterLabels
     * @return array<int, string>
     */
    public static function getAvailableShifts(
        string $noSampel,
        array $orderParameterLabels,
        string $modelClass,
        int $maxShift = 25,
        $detailRows = null
    ): array {
        if ($detailRows === null) {
            $detailRows = self::fetchDetailRows($noSampel, $modelClass);
        }
        $available = [];

        for ($i = 1; $i <= $maxShift; $i++) {
            $shift = 'L' . $i;
            if (count(self::buildPendingParametersForShift(
                $noSampel,
                $shift,
                $orderParameterLabels,
                $modelClass,
                $detailRows
            )) > 0) {
                $available[] = $shift;
            }
        }

        return $available;
    }

    /** Kategori pengujian dari nama parameter (case-insensitive). */
    public static function resolveKategoriFromParameterName(string $parameter): ?string
    {
        $lower = strtolower($parameter);

        if (str_contains($lower, '24 jam') || str_contains($lower, '24j')) {
            return '24 Jam';
        }
        if (str_contains($lower, '8 jam') || str_contains($lower, '8j')) {
            return '8 Jam';
        }
        if (str_contains($lower, '6 jam') || str_contains($lower, '6j')) {
            return '6 Jam';
        }
        if (str_contains($lower, '3 jam')) {
            return '3 Jam';
        }

        return null;
    }

    /**
     * Gabungkan kateg_uji dari request dengan fallback nama parameter.
     *
     * @param mixed $kategUjiFromRequest
     */
    public static function resolveKategoriForStore($kategUjiFromRequest, string $parameterName): ?string
    {
        $fromRequest = is_string($kategUjiFromRequest) ? trim($kategUjiFromRequest) : '';
        if ($fromRequest !== '' && $fromRequest !== '0') {
            $fromRequestKategori = self::resolveKategoriFromParameterName($fromRequest);
            if ($fromRequestKategori !== null) {
                return $fromRequestKategori;
            }

            $normalized = strtolower($fromRequest);
            $map = [
                '24 jam' => '24 Jam',
                '8 jam' => '8 Jam',
                '6 jam' => '6 Jam',
                '3 jam' => '3 Jam',
            ];
            if (isset($map[$normalized])) {
                return $map[$normalized];
            }
        }

        return self::resolveKategoriFromParameterName($parameterName);
    }

    /** @return array{kategori_pengujian: string, shift_pengambilan: string} */
    public static function resolveShiftFieldsForStore(?string $kategUji, string $shiftPengambilan): array
    {
        if ($kategUji === null || $kategUji === '') {
            return [
                'kategori_pengujian' => 'Sesaat',
                'shift_pengambilan' => 'Sesaat',
            ];
        }

        return [
            'kategori_pengujian' => $kategUji . '-' . json_encode($shiftPengambilan),
            'shift_pengambilan' => $shiftPengambilan,
        ];
    }

    public static function parameterHasDurasiInName(string $parameter): bool
    {
        return self::resolveKategoriFromParameterName($parameter) !== null;
    }

    private static function normalizeParameterLabel(string $label): string
    {
        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $label)));
        $normalized = str_replace('kelembapan', 'kelembaban', $normalized);

        return $normalized;
    }

    public static function orderParameterMatchesCanonical(string $orderParameter, string $canonical): bool
    {
        if ($orderParameter === null || $orderParameter === '') {
            return false;
        }

        if ($orderParameter === $canonical) {
            return true;
        }

        return self::normalizeParameterLabel($orderParameter) === self::normalizeParameterLabel($canonical);
    }

    /**
     * Cocokkan parameter order dengan whitelist ambient (nama canonical dari whitelist).
     *
     * @param array<int, string|null> $orderParameters
     * @return array<int, string>
     */
    public static function matchAmbientParametersFromOrder(array $orderParameters): array
    {
        $matched = [];

        foreach (self::ambientStoreParameters() as $canonical) {
            foreach ($orderParameters as $orderParameter) {
                if (self::orderParameterMatchesCanonical($orderParameter, $canonical)) {
                    $matched[] = $canonical;
                    break;
                }
            }
        }

        return $matched;
    }
}
