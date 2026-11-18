<?php

namespace App\Support;

/**
 * Skema JSON investigasi awal — bagian D (penyebab) & E (dampak).
 */
final class InvestigasiAnalisisKasus
{
    public const VERSION = 1;

    /** @var list<string> */
    private const PENYEBAB_KEYS = ['hmf', 'sys', 'tec', 'ext', 'lainnya'];

    /**
     * @param  array<string, mixed>  $input  Request / payload flat atau nested
     * @return array<string, mixed>
     */
    public static function buildFromInput(array $input): array
    {
        if (isset($input['analisis_kasus']) && is_array($input['analisis_kasus'])) {
            return self::normalize($input['analisis_kasus']);
        }

        $built = [
            'v' => self::VERSION,
            'penyebab' => [
                'hmf' => self::penyebabNode($input, 'hmf'),
                'sys' => self::penyebabNode($input, 'sys'),
                'tec' => self::penyebabNode($input, 'tec'),
                'ext' => self::penyebabNode($input, 'ext'),
                'lainnya' => self::penyebabNode($input, 'lainnya'),
            ],
            'dampak' => [
                'fin' => [
                    'on' => self::boolVal($input['dampak_fin'] ?? false),
                    'aset' => self::boolVal($input['dampak_fin_aset'] ?? false),
                ],
                'ops' => [
                    'on' => self::boolVal($input['dampak_ops'] ?? false),
                    'delay_layanan' => self::boolVal($input['dampak_ops_delay_layanan'] ?? false),
                ],
                'hse' => self::hseNodeFromFlatInput($input),
                'leg' => [
                    'on' => self::boolVal($input['dampak_leg'] ?? false),
                    'risiko_sanksi' => self::boolVal($input['dampak_leg_risiko_sanksi'] ?? false),
                ],
                'lainnya' => [
                    'on' => self::boolVal($input['dampak_lainnya'] ?? false),
                    'note' => self::textOrNull($input['dampak_lainnya_keterangan'] ?? null),
                ],
                'catatan_khusus' => self::textOrNull($input['dampak_catatan_khusus'] ?? null),
            ],
        ];

        return self::normalize($built);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data): array
    {
        $penyebabIn = is_array($data['penyebab'] ?? null) ? $data['penyebab'] : [];
        $dampakIn = is_array($data['dampak'] ?? null) ? $data['dampak'] : [];

        $penyebab = [];
        foreach (self::PENYEBAB_KEYS as $key) {
            $node = is_array($penyebabIn[$key] ?? null) ? $penyebabIn[$key] : [];
            $on = self::boolVal($node['on'] ?? false);
            $penyebab[$key] = [
                'on' => $on,
                'note' => $on ? self::textOrNull($node['note'] ?? null) : null,
            ];
        }

        $fin = is_array($dampakIn['fin'] ?? null) ? $dampakIn['fin'] : [];
        $ops = is_array($dampakIn['ops'] ?? null) ? $dampakIn['ops'] : [];
        $hse = is_array($dampakIn['hse'] ?? null) ? $dampakIn['hse'] : [];
        $leg = is_array($dampakIn['leg'] ?? null) ? $dampakIn['leg'] : [];
        $lain = is_array($dampakIn['lainnya'] ?? null) ? $dampakIn['lainnya'] : [];

        $hse = self::hseNodeFromArray($hse);
        $lainOn = self::boolVal($lain['on'] ?? false);

        return [
            'v' => (int) ($data['v'] ?? self::VERSION),
            'penyebab' => $penyebab,
            'dampak' => [
                'fin' => [
                    'on' => self::boolVal($fin['on'] ?? false),
                    'aset' => self::boolVal($fin['aset'] ?? false),
                ],
                'ops' => [
                    'on' => self::boolVal($ops['on'] ?? false),
                    'delay_layanan' => self::boolVal($ops['delay_layanan'] ?? false),
                ],
                'hse' => $hse,
                'leg' => [
                    'on' => self::boolVal($leg['on'] ?? false),
                    'risiko_sanksi' => self::boolVal($leg['risiko_sanksi'] ?? false),
                ],
                'lainnya' => [
                    'on' => $lainOn,
                    'note' => $lainOn ? self::textOrNull($lain['note'] ?? null) : null,
                ],
                'catatan_khusus' => self::textOrNull($dampakIn['catatan_khusus'] ?? null),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateStructure(array $data): ?string
    {
        $version = (int) ($data['v'] ?? 0);
        if ($version < 1 || $version > self::VERSION) {
            return 'Versi analisis kasus tidak didukung.';
        }

        if (!is_array($data['penyebab'] ?? null) || !is_array($data['dampak'] ?? null)) {
            return 'Struktur analisis kasus tidak valid (penyebab/dampak).';
        }

        foreach (self::PENYEBAB_KEYS as $key) {
            if (!is_array($data['penyebab'][$key] ?? null)) {
                return 'Struktur penyebab tidak valid.';
            }
        }

        foreach (['fin', 'ops', 'hse', 'leg', 'lainnya'] as $key) {
            if (!is_array($data['dampak'][$key] ?? null)) {
                return 'Struktur dampak tidak valid.';
            }
        }

        return null;
    }

    public static function encode(array $input): string
    {
        $normalized = self::buildFromInput($input);
        $err = self::validateStructure($normalized);
        if ($err !== null) {
            throw new \InvalidArgumentException($err);
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeFromStorage($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalize($decoded);
            }
        }
        if (is_array($raw)) {
            return self::normalize($raw);
        }

        return self::empty();
    }

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return self::normalize(['v' => self::VERSION, 'penyebab' => [], 'dampak' => []]);
    }

    /**
     * Ringkasan indeks — label lengkap (bukan kode HMF/SYS/OPS).
     *
     * @param  array<string, mixed>  $analisis
     */
    public static function summarizePenyebabForDisplay(array $analisis): string
    {
        $penyebab = $analisis['penyebab'] ?? [];
        $map = [
            'hmf' => 'Human Factor',
            'sys' => 'System Failure',
            'tec' => 'Technical / Equipment',
            'ext' => 'External',
            'lainnya' => 'Lainnya',
        ];
        $list = [];
        foreach ($map as $key => $label) {
            if (!empty($penyebab[$key]['on'])) {
                $list[] = $label;
            }
        }

        return $list !== [] ? implode(', ', $list) : '-';
    }

    /**
     * @param  array<string, mixed>  $analisis
     */
    public static function summarizeDampakForDisplay(array $analisis): string
    {
        $dampak = $analisis['dampak'] ?? [];
        $list = [];
        $fin = is_array($dampak['fin'] ?? null) ? $dampak['fin'] : [];
        $ops = is_array($dampak['ops'] ?? null) ? $dampak['ops'] : [];
        $hse = is_array($dampak['hse'] ?? null) ? $dampak['hse'] : [];
        $leg = is_array($dampak['leg'] ?? null) ? $dampak['leg'] : [];
        $lain = is_array($dampak['lainnya'] ?? null) ? $dampak['lainnya'] : [];

        if (!empty($fin['on'])) {
            $list[] = 'Kerugian Langsung';
        }
        if (!empty($fin['aset'])) {
            $list[] = 'Aset';
        }
        if (!empty($ops['on'])) {
            $list[] = 'Gangguan Proses Kerja';
        }
        if (!empty($ops['delay_layanan'])) {
            $list[] = 'Delay Layanan';
        }
        if (!empty($hse['near_miss'])) {
            $list[] = 'Near Miss';
        }
        if (!empty($hse['injury'])) {
            $list[] = 'Injury';
        }
        if (!empty($hse['fatality'])) {
            $list[] = 'Fatality';
        }
        if (!empty($leg['on'])) {
            $list[] = 'Pelanggaran Peraturan';
        }
        if (!empty($leg['risiko_sanksi'])) {
            $list[] = 'Risiko sanksi hukum';
        }
        if (!empty($lain['on'])) {
            $list[] = 'Lainnya';
        }

        return $list !== [] ? implode(', ', $list) : '-';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{on: bool, note: ?string}
     */
    private static function penyebabNode(array $input, string $key): array
    {
        $on = self::boolVal($input['penyebab_' . $key] ?? false);
        $noteKey = 'penyebab_' . $key . '_keterangan';

        return [
            'on' => $on,
            'note' => $on ? self::textOrNull($input[$noteKey] ?? null) : null,
        ];
    }

    private static function boolVal($value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        return false;
    }

    private static function textOrNull($value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        return $raw !== '' ? $raw : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{on: bool, near_miss: bool, injury: bool, fatality: bool}
     */
    private static function hseNodeFromFlatInput(array $input): array
    {
        $near = self::boolVal($input['dampak_hse_near_miss'] ?? false);
        $injury = self::boolVal($input['dampak_hse_injury'] ?? false);
        $fatality = self::boolVal($input['dampak_hse_fatality'] ?? false);
        $on = $near || $injury || $fatality || self::boolVal($input['dampak_hse'] ?? false);

        return [
            'on' => $on,
            'near_miss' => $near,
            'injury' => $injury,
            'fatality' => $fatality,
        ];
    }

    /**
     * @param  array<string, mixed>  $hse
     * @return array{on: bool, near_miss: bool, injury: bool, fatality: bool}
     */
    private static function hseNodeFromArray(array $hse): array
    {
        $near = self::boolVal($hse['near_miss'] ?? false);
        $injury = self::boolVal($hse['injury'] ?? false);
        $fatality = self::boolVal($hse['fatality'] ?? false);
        $on = $near || $injury || $fatality || self::boolVal($hse['on'] ?? false);

        return [
            'on' => $on,
            'near_miss' => $near,
            'injury' => $injury,
            'fatality' => $fatality,
        ];
    }
}