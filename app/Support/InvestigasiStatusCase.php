<?php

namespace App\Support;

/**
 * Tindak lanjut awal (G.1) → Status Case pada dokumen F1.
 */
final class InvestigasiStatusCase
{
    public const DSC_TANPA_SANKSI = 'DSC-TS';

    public const DSC_DENGAN_SANKSI = 'DSC-DS';

    public const KOMITE_MANAJEMEN = 'KM';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return [
            self::DSC_TANPA_SANKSI,
            self::DSC_DENGAN_SANKSI,
            self::KOMITE_MANAJEMEN,
        ];
    }

    public static function normalize($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        return in_array($raw, self::codes(), true) ? $raw : null;
    }

    public static function label(?string $code): string
    {
        switch (self::normalize($code)) {
            case self::DSC_TANPA_SANKSI:
                return 'Internal Disciplinary (Tanpa Sanksi)';
            case self::DSC_DENGAN_SANKSI:
                return 'Internal Disciplinary (Dengan Sanksi)';
            case self::KOMITE_MANAJEMEN:
                return 'Komite Manajemen';
            default:
                return '-';
        }
    }

    /**
     * @param  array<string, mixed>  $g1
     */
    public static function resolveFromG1(array $g1): ?string
    {
        $direct = self::normalize($g1['status_case'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        return self::resolveFromLegacy(
            $g1['rekomendasi_alur'] ?? null,
            $g1['keputusan_sanksi'] ?? null,
            $g1['tindak_lanjut_awal'] ?? null
        );
    }

    /**
     * @param  array<string, mixed>|object  $row
     */
    public static function resolveFromRow($row): ?string
    {
        $arr = is_array($row) ? $row : (array) $row;
        $direct = self::normalize($arr['status_case'] ?? null);
        if ($direct !== null) {
            return $direct;
        }

        return self::resolveFromLegacy(
            $arr['rekomendasi_alur'] ?? null,
            $arr['keputusan_sanksi'] ?? null,
            $arr['tindak_lanjut_awal'] ?? null
        );
    }

    /**
     * @return array{rekomendasi_alur: ?string, keputusan_sanksi: ?string, tindak_lanjut_awal: ?string}
     */
    public static function legacyFieldsFromStatusCase(?string $statusCase): array
    {
        $code = self::normalize($statusCase);

        if ($code === self::KOMITE_MANAJEMEN) {
            return [
                'rekomendasi_alur' => 'komite_manajemen',
                'keputusan_sanksi' => 'tanpa_sanksi',
                'tindak_lanjut_awal' => 'komite_manajemen',
            ];
        }

        if ($code === self::DSC_DENGAN_SANKSI) {
            return [
                'rekomendasi_alur' => 'internal_disciplinary',
                'keputusan_sanksi' => 'dengan_sanksi',
                'tindak_lanjut_awal' => 'internal_disciplinary',
            ];
        }

        if ($code === self::DSC_TANPA_SANKSI) {
            return [
                'rekomendasi_alur' => 'internal_disciplinary',
                'keputusan_sanksi' => 'tanpa_sanksi',
                'tindak_lanjut_awal' => 'tanpa_sanksi',
            ];
        }

        return [
            'rekomendasi_alur' => null,
            'keputusan_sanksi' => null,
            'tindak_lanjut_awal' => null,
        ];
    }

    public static function requiresSanksiDetail(?string $statusCase): bool
    {
        return self::normalize($statusCase) === self::DSC_DENGAN_SANKSI;
    }

    private static function resolveFromLegacy($alur, $sanksi, $tindakLanjutAwal): ?string
    {
        $alur = trim((string) ($alur ?? ''));
        $sanksi = trim((string) ($sanksi ?? ''));
        $legacy = trim((string) ($tindakLanjutAwal ?? ''));

        if ($alur === 'komite_manajemen' || $legacy === 'komite_manajemen') {
            return self::KOMITE_MANAJEMEN;
        }

        if ($alur === 'internal_disciplinary' || $legacy === 'internal_disciplinary') {
            return $sanksi === 'dengan_sanksi' ? self::DSC_DENGAN_SANKSI : self::DSC_TANPA_SANKSI;
        }

        if ($legacy === 'tanpa_sanksi') {
            return self::DSC_TANPA_SANKSI;
        }

        return null;
    }
}