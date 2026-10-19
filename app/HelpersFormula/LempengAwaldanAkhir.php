<?php

namespace App\HelpersFormula;
/**
 * Perhitungan koloni lempeng awal / akhir (dua kolom suhu bebas input).
 *
 * Input (object $data):
 *
 * {
 *   "volume_sampel": 1,
 *   "suhu_kolom_1": "36°C",
 *   "suhu_kolom_2": "22°C",
 *   "lempeng": [
 *     {
 *       // pengenceran = pangkat (eksponen) pada label tabel analis, BUKAN faktor d.
 *       // Contoh: 0 → 10⁰, 2 → 10². Faktor d untuk rumus = 10^(-pengenceran) → 10² ⇒ d = 0,01.
 *       "pengenceran": 2,
 *       "kolom_1": { "suhu": "36°C", "jumlah_coloni": 263, "volume_uji": 1 },
 *       "kolom_2": { "suhu": "22°C", "jumlah_coloni": 311, "volume_uji": 1 }
 *     }
 *   ]
 * }
 *
 * Legacy (array paralel): jumlah_coloni_36[], jumlah_coloni_22[] (atau jumlah_coloni_kolom_*)
 *
 * function di Mdl / AnalystFormula: LempengAwaldanAkhir
 */
class LempengAwaldanAkhir
{
    public const MIN_COLONI = 10;
    public const MAX_COLONI = 300;
    /** @var int[] */
    private const DILUTION_EXPONENTS = [0, 1, 2, 3, 4, 5, 6];
    public function index($data, $id_parameter, $mdl)
    {
        $vs = $this->resolveVolumeSampel($data);
        $readings = $this->normalizeReadings($data);
        if ($readings === []) {
            throw new \InvalidArgumentException('Data lempeng kosong. Isi lempeng[] atau array jumlah_coloni_kolom_1 / jumlah_coloni_kolom_2.');
        }
        $calc = $this->calculate($readings, $vs);
        $rawInput = $this->buildRawInputSnapshot($data);
        $hasilNumeric = $calc['cs_dibulatkan'];
        $hasil = $this->formatScientificIndonesia($hasilNumeric);
        if ($mdl !== null && $mdl !== '' && is_numeric($mdl) && $calc['cs_mentah'] > 0 && $calc['cs_dibulatkan'] < (float) $mdl) {
            $hasil = '<' . $mdl;
        }
        $result = [
            'hasil' => $hasil,
            'hasil_2' => $calc['cs_mentah'],
            'rpd' => '',
            'recovery' => '',
            'satuan' => 'CFU/mL',
            'vtot' => $calc['vtot'],
            'z' => $calc['z'],
            'vs' => $vs,
            'nilai_tambahan_analyst' => json_encode([
                'data_input_mentah' => $rawInput,
            ], JSON_UNESCAPED_UNICODE),
        ];
        dd($result);

        return $result;
    }
    /**
     * @param list<array{exponent: int, suhu: string, coloni: float, volume: float}> $readings
     * @return array{vtot: float, z: float, cs_mentah: float, cs_dibulatkan: float, valid: list<array<string, mixed>>, ditolak: list<array<string, mixed>>}
     */
    public function calculate(array $readings, float $vs): array
    {
        $valid = [];
        $ditolak = [];
        foreach ($readings as $row) {
            $coloni = $row['coloni'];
            $entry = [
                'pengenceran_pangkat' => $row['exponent'],
                'pengenceran' => $this->formatPengenceranDisplay($row['exponent']),
                'suhu' => $row['suhu'],
                'jumlah_coloni' => $coloni,
                'volume_uji_ml' => $row['volume'],
                'd' => $this->dilutionFactor($row['exponent']),
            ];
            if (!$this->isValidColonyCount($coloni)) {
                $entry['alasan'] = $coloni < self::MIN_COLONI ? 'di bawah 10 CFU' : 'di atas 300 CFU';
                $ditolak[] = $entry;
                continue;
            }
            $valid[] = $entry;
        }
        $aggregated = $this->aggregateVtotByPengenceran($valid);
        $vtot = round($aggregated['vtot'], 4);
        $z = $aggregated['z'];
        $csMentah = 0.0;
        if ($vtot > 0 && $z > 0) {
            $csMentah = ($z / $vtot) * $vs;
        }
        return [
            'vtot' => $vtot,
            'z' => $z,
            'cs_mentah' => $csMentah,
            'cs_dibulatkan' => $this->roundTwoSignificantFigures($csMentah),
            'valid' => $valid,
            'ditolak' => $ditolak,
            'rekap_vtot_per_pengenceran' => $aggregated['rekap'],
        ];
    }
    /**
     * Vtot = Σ (ni × Vi × di) per tingkat pengenceran.
     * ni = jumlah cawan valid (10–300 CFU) pada pangkat yang sama, dijumlah antar kolom suhu.
     * Jika Vi berbeda per cawan: kontribusi = Σ(Vi × di), setara Σ ni×Vi×di bila Vi sama.
     *
     * @param list<array<string, mixed>> $valid
     * @return array{vtot: float, z: float, rekap: list<array<string, mixed>>}
     */
    private function aggregateVtotByPengenceran(array $valid): array
    {
        /** @var array<int, array<string, mixed>> $groups */
        $groups = [];
        foreach ($valid as $entry) {
            $entry['jumlah_cawan'] = 1;
            $exp = (int) $entry['pengenceran_pangkat'];
            $kontribusi = (float) $entry['volume_uji_ml'] * (float) $entry['d'];
            if (!isset($groups[$exp])) {
                $groups[$exp] = [
                    'pengenceran_pangkat' => $exp,
                    'pengenceran' => $entry['pengenceran'],
                    'd' => $entry['d'],
                    'jumlah_cawan' => 0,
                    'jumlah_coloni' => 0.0,
                    'kontribusi_vtot' => 0.0,
                    'cawan' => [],
                ];
            }
            $groups[$exp]['jumlah_cawan']++;
            $groups[$exp]['jumlah_coloni'] += (float) $entry['jumlah_coloni'];
            $groups[$exp]['kontribusi_vtot'] += $kontribusi;
            $groups[$exp]['cawan'][] = [
                'suhu' => $entry['suhu'],
                'jumlah_coloni' => $entry['jumlah_coloni'],
                'volume_uji_ml' => $entry['volume_uji_ml'],
            ];
        }
        ksort($groups);
        $vtot = 0.0;
        $z = 0.0;
        $rekap = [];
        foreach ($groups as $group) {
            $ni = (int) $group['jumlah_cawan'];
            $vtot += (float) $group['kontribusi_vtot'];
            $z += (float) $group['jumlah_coloni'];
            $group['kontribusi_vtot'] = round((float) $group['kontribusi_vtot'], 4);
            $group['jumlah_coloni'] = $group['jumlah_coloni'];
            $rekap[] = $group;
        }
        return [
            'vtot' => $vtot,
            'z' => $z,
            'rekap' => $rekap,
        ];
    }
    public function dilutionFactor(int $exponent): float
    {
        return 10 ** (-$exponent);
    }
    public function isValidColonyCount($coloni): bool
    {
        if ($coloni === null || $coloni === '') {
            return false;
        }
        $n = (float) $coloni;
        return $n >= self::MIN_COLONI && $n <= self::MAX_COLONI;
    }
    /**
     * @return list<array{exponent: int, suhu: string, coloni: float, volume: float}>
     */
    public function normalizeReadings($data): array
    {
        $data = is_object($data) ? $data : (object) $data;
        $out = [];
        if (isset($data->lempeng) && (is_array($data->lempeng) || is_object($data->lempeng))) {
            foreach ((array) $data->lempeng as $row) {
                $row = is_object($row) ? $row : (object) $row;
                $exponent = $this->parseExponent($row->pengenceran ?? $row->tingkat ?? null);
                if ($exponent === null) {
                    continue;
                }
                foreach ($this->columnDefinitions($data) as $column) {
                    $block = $this->resolveRowBlock($row, $column);
                    $this->appendReadingFromBlock($out, $exponent, $column['suhu_label'], $block);
                }
            }
            return $out;
        }
        foreach ($this->columnDefinitions($data) as $column) {
            $label = $column['suhu_label'];
            $coloniKey = $this->firstExistingKey($data, $column['coloni_keys']);
            $volumeKey = $this->firstExistingKey($data, $column['volume_keys']);
            if ($coloniKey === null) {
                continue;
            }
            $coloniList = $this->toList($data->{$coloniKey});
            $volumeList = $volumeKey !== null ? $this->toList($data->{$volumeKey}) : [];
            foreach (self::DILUTION_EXPONENTS as $idx => $exponent) {
                if (!array_key_exists($idx, $coloniList)) {
                    continue;
                }
                $coloni = $coloniList[$idx];
                if ($coloni === null || $coloni === '') {
                    continue;
                }
                $volume = 1.0;
                if (array_key_exists($idx, $volumeList) && $volumeList[$idx] !== null && $volumeList[$idx] !== '') {
                    $volume = (float) $volumeList[$idx];
                }
                $out[] = [
                    'exponent' => $exponent,
                    'suhu' => $label,
                    'coloni' => (float) $coloni,
                    'volume' => $volume,
                ];
            }
        }
        return $out;
    }
    /**
     * @param list<array{exponent: int, suhu: string, coloni: float, volume: float}> $out
     */
    private function appendReadingFromBlock(array &$out, int $exponent, string $defaultSuhuLabel, $block): void
    {
        if ($block === null) {
            return;
        }
        $block = is_object($block) ? $block : (object) $block;
        $coloni = $block->jumlah_coloni ?? $block->coloni ?? null;
        if ($coloni === null || $coloni === '') {
            return;
        }
        $volume = $block->volume_uji ?? $block->volume ?? 1;
        $label = $defaultSuhuLabel;
        if (isset($block->suhu) && $block->suhu !== '') {
            $label = (string) $block->suhu;
        }
        $out[] = [
            'exponent' => $exponent,
            'suhu' => $label,
            'coloni' => (float) $coloni,
            'volume' => (float) $volume,
        ];
    }
    /**
     * Definisi kolom input (selaras frontend: kolom_1 / kolom_2 + suhu_kolom_*).
     *
     * @return list<array{
     *   id: string,
     *   suhu_label: string,
     *   row_keys: list<string>,
     *   coloni_keys: list<string>,
     *   volume_keys: list<string>
     * }>
     */
    private function columnDefinitions($data): array
    {
        $data = is_object($data) ? $data : (object) $data;
        $suhu1 = isset($data->suhu_kolom_1) && $data->suhu_kolom_1 !== ''
            ? (string) $data->suhu_kolom_1
            : '36°C';
        $suhu2 = isset($data->suhu_kolom_2) && $data->suhu_kolom_2 !== ''
            ? (string) $data->suhu_kolom_2
            : '22°C';
        return [
            [
                'id' => 'kolom_1',
                'suhu_label' => $suhu1,
                'row_keys' => ['kolom_1'],
                'coloni_keys' => ['jumlah_coloni_kolom_1', 'jumlah_coloni_36'],
                'volume_keys' => ['volume_uji_kolom_1', 'volume_uji_36'],
            ],
            [
                'id' => 'kolom_2',
                'suhu_label' => $suhu2,
                'row_keys' => ['kolom_2'],
                'coloni_keys' => ['jumlah_coloni_kolom_2', 'jumlah_coloni_22'],
                'volume_keys' => ['volume_uji_kolom_2', 'volume_uji_22'],
            ],
        ];
    }
    /**
     * @param object $row
     * @param array{id: string, suhu_label: string, row_keys: list<string>, coloni_keys: list<string>, volume_keys: list<string>} $column
     */
    private function resolveRowBlock(object $row, array $column): ?object
    {
        foreach ($column['row_keys'] as $key) {
            if (!isset($row->{$key})) {
                continue;
            }
            $raw = $row->{$key};
            if ($raw === null || $raw === '') {
                continue;
            }
            return is_object($raw) ? $raw : (object) $raw;
        }
        $coloniKey = $this->firstExistingKey($row, $column['coloni_keys']);
        if ($coloniKey === null) {
            return null;
        }
        $coloni = $row->{$coloniKey} ?? null;
        if ($coloni === null || $coloni === '') {
            return null;
        }
        $volumeKey = $this->firstExistingKey($row, $column['volume_keys']);
        $volume = $volumeKey !== null ? ($row->{$volumeKey} ?? '') : '';
        return (object) [
            'suhu' => $column['suhu_label'],
            'jumlah_coloni' => $coloni,
            'volume_uji' => $volume !== '' ? $volume : 1,
        ];
    }
    /**
     * @param object $data
     * @param list<string> $keys
     */
    private function firstExistingKey($data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data->{$key})) {
                return $key;
            }
        }
        return null;
    }
    /**
     * @return array<string, mixed>
     */
    public function buildRawInputSnapshot($data): array
    {
        $data = is_object($data) ? $data : (object) $data;
        $vs = $this->resolveVolumeSampel($data);
        $columns = $this->columnDefinitions($data);
        if (isset($data->data_input_mentah) && (is_array($data->data_input_mentah) || is_object($data->data_input_mentah))) {
            return (array) $data->data_input_mentah;
        }
        $baris = [];
        if (isset($data->lempeng) && (is_array($data->lempeng) || is_object($data->lempeng))) {
            foreach ((array) $data->lempeng as $row) {
                $row = is_object($row) ? $row : (object) $row;
                $exponent = $this->parseExponent($row->pengenceran ?? $row->tingkat ?? null);
                if ($exponent === null) {
                    continue;
                }
                $barisEntry = [
                    'pengenceran_pangkat' => $exponent,
                    'pengenceran' => $this->formatPengenceranDisplay($exponent),
                ];
                foreach ($columns as $column) {
                    $barisEntry[$column['id']] = $this->rawBlockFromColumn($row, $column);
                }
                $baris[] = $barisEntry;
            }
        }
        return [
            'volume_sampel_vs_ml' => $vs,
            'suhu_kolom_1' => $columns[0]['suhu_label'],
            'suhu_kolom_2' => $columns[1]['suhu_label'],
            'baris' => $baris,
        ];
    }
    /**
     * @param array{id: string, suhu_label: string, row_keys: list<string>, coloni_keys: list<string>, volume_keys: list<string>} $column
     * @return array{suhu: string, jumlah_coloni: mixed, volume_uji_ml: mixed}
     */
    private function rawBlockFromColumn(object $row, array $column): array
    {
        $block = $this->resolveRowBlock($row, $column);
        if ($block === null) {
            return [
                'suhu' => $column['suhu_label'],
                'jumlah_coloni' => '',
                'volume_uji_ml' => '',
            ];
        }
        return [
            'suhu' => (isset($block->suhu) && $block->suhu !== '') ? (string) $block->suhu : $column['suhu_label'],
            'jumlah_coloni' => $block->jumlah_coloni ?? $block->coloni ?? '',
            'volume_uji_ml' => $block->volume_uji ?? $block->volume ?? '',
        ];
    }
    /**
     * @param list<array<string, mixed>> $entries
     * @return list<array<string, mixed>>
     */
    private function withSuperscriptPengenceranList(array $entries): array
    {
        foreach ($entries as &$entry) {
            if (isset($entry['pengenceran_pangkat'])) {
                $entry['pengenceran'] = $this->formatPengenceranDisplay((int) $entry['pengenceran_pangkat']);
            }
        }
        unset($entry);
        return $entries;
    }
    public function formatPengenceranDisplay(int $exponent): string
    {
        return '10' . $this->formatExponentSuperscript($exponent);
    }
    public function formatExponentSuperscript(int $exponent): string
    {
        $map = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹'];
        $sign = $exponent < 0 ? '⁻' : '';
        $digits = str_split((string) abs($exponent));
        return $sign . implode('', array_map(static fn ($d) => $map[$d] ?? $d, $digits));
    }
    private function resolveVolumeSampel($data): float
    {
        $data = is_object($data) ? $data : (object) $data;
        foreach (['volume_sampel', 'vs', 'volume_sampel_vs'] as $key) {
            if (isset($data->{$key}) && $data->{$key} !== '') {
                return (float) $data->{$key};
            }
        }
        return 1.0;
    }
    /**
     * @param mixed $value
     * @return list<mixed>
     */
    private function toList($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_object($value)) {
            return array_values((array) $value);
        }
        return [$value];
    }
    private function parseExponent($raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) && $raw >= 0 && $raw <= 6) {
            return $raw;
        }
        if (is_numeric($raw)) {
            $n = (int) $raw;
            return ($n >= 0 && $n <= 6) ? $n : null;
        }
        $s = trim((string) $raw);
        if (preg_match('/10\s*\^\s*(\d)/', $s, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^(\d)$/', $s, $m)) {
            return (int) $m[1];
        }
        return null;
    }
    /**
     * Dua angka bena (signifikan) sesuai ketentuan pelaporan:
     * digit ke-3 < 5 → turunkan; digit ke-3 > 5 → naikkan; digit ke-3 = 5 → turunkan.
     */
    public function roundTwoSignificantFigures(float $value): float
    {
        if ($value == 0.0) {
            return 0.0;
        }
        $sign = $value < 0 ? -1.0 : 1.0;
        $abs = abs($value);
        $magnitude = (int) floor(log10($abs));
        $pow = 10 ** ($magnitude - 1);
        $normalized = $abs / $pow;
        $thirdDigit = (int) (floor($normalized * 10) % 10);
        $twoDigit = (int) floor($normalized);
        if ($thirdDigit > 5) {
            $twoDigit += 1;
        }
        return $sign * $twoDigit * $pow;
    }
    public function formatScientificIndonesia(float $value): string
    {
        if ($value == 0.0) {
            return '0';
        }
        $rounded = $this->roundTwoSignificantFigures($value);
        $exponent = (int) floor(log10(abs($rounded)));
        $mantissa = $rounded / (10 ** $exponent);
        $mantissaStr = rtrim(rtrim(number_format($mantissa, 2, '.', ''), '0'), '.');
        return $mantissaStr . 'x10' . $this->formatExponentSuperscript($exponent);
    }
}
