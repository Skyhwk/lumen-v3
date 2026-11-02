<?php

namespace App\Support;

final class BeritaAcaraKejadianDampak
{
    public const SECTION_TITLE = 'Yang berdampak pada aset perusahaan dengan data sebagai berikut :';

    /** @var list<string> */
    public const KEYS = [
        'nama_jenis_aset',
        'no_aset_no_plat',
        'merek_tipe_aset',
        'jumlah_unit_aset',
        'tujuan_pemakaian_aset',
        'jenis_kerusakan_aset',
        'bagian_kerusakan_aset',
    ];

    /** @var list<string> */
    private const LEGACY_KEYS = [
        'berdampak',
        'finansial',
        'kerusakan_barang',
        'hambatan_operasional',
        'biaya_pemulihan',
        'nilai_manfaat',
        'kepercayaan_risiko',
    ];

    /**
     * @return array<string, string>
     */
    public static function emptyState(): array
    {
        $state = [];
        foreach (self::KEYS as $key) {
            $state[$key] = '';
        }

        return $state;
    }

    /**
     * @param mixed $raw
     * @return array<string, string>
     */
    public static function parse($raw): array
    {
        $empty = self::emptyState();

        if ($raw === null || $raw === '') {
            return $empty;
        }

        if (is_string($raw)) {
            $trim = trim($raw);
            if ($trim === '' || $trim[0] !== '{') {
                return $empty;
            }
            $decoded = json_decode($trim, true);
        } elseif (is_array($raw)) {
            $decoded = $raw;
        } else {
            return $empty;
        }

        if (!is_array($decoded)) {
            return $empty;
        }

        foreach (self::LEGACY_KEYS as $legacyKey) {
            if (array_key_exists($legacyKey, $decoded) && !array_key_exists('nama_jenis_aset', $decoded)) {
                return $empty;
            }
        }

        $jenis = (string) ($decoded['jenis_kerusakan_aset'] ?? '');
        if (!in_array($jenis, ['sebagian', 'seluruhnya'], true)) {
            $jenis = '';
        }

        return [
            'nama_jenis_aset' => trim((string) ($decoded['nama_jenis_aset'] ?? '')),
            'no_aset_no_plat' => trim((string) ($decoded['no_aset_no_plat'] ?? '')),
            'merek_tipe_aset' => trim((string) ($decoded['merek_tipe_aset'] ?? '')),
            'jumlah_unit_aset' => trim((string) ($decoded['jumlah_unit_aset'] ?? '')),
            'tujuan_pemakaian_aset' => trim((string) ($decoded['tujuan_pemakaian_aset'] ?? '')),
            'jenis_kerusakan_aset' => $jenis,
            'bagian_kerusakan_aset' => trim((string) ($decoded['bagian_kerusakan_aset'] ?? '')),
        ];
    }

    /**
     * @param mixed $raw
     * @return array{ok: true, json: string|null}|array{ok: false, message: string}
     */
    public static function encodeFromRequest($raw): array
    {
        $parsed = self::parse($raw);

        if ($parsed['nama_jenis_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Nama / Jenis Aset wajib diisi.',
            ];
        }
        if ($parsed['merek_tipe_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Merek / Tipe Aset wajib diisi.',
            ];
        }
        if ($parsed['jumlah_unit_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Jumlah Unit Aset wajib diisi.',
            ];
        }
        if ($parsed['tujuan_pemakaian_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Tujuan Pemakaian Aset wajib diisi.',
            ];
        }
        if ($parsed['jenis_kerusakan_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Pilih Jenis Kerusakan Aset (sebagian atau seluruhnya).',
            ];
        }
        if ($parsed['bagian_kerusakan_aset'] === '') {
            return [
                'ok' => false,
                'message' => 'Bagian Kerusakan Aset wajib diisi.',
            ];
        }

        foreach (['nama_jenis_aset', 'no_aset_no_plat', 'merek_tipe_aset', 'jumlah_unit_aset', 'tujuan_pemakaian_aset', 'bagian_kerusakan_aset'] as $key) {
            if (mb_strlen($parsed[$key]) > 500) {
                return [
                    'ok' => false,
                    'message' => 'Isian dampak aset maksimal 500 karakter per field.',
                ];
            }
        }

        return [
            'ok' => true,
            'json' => json_encode($parsed, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function formatJenisKerusakanLabel(string $value): string
    {
        if ($value === 'sebagian') {
            return 'sebagian';
        }
        if ($value === 'seluruhnya') {
            return 'seluruhnya';
        }

        return '-';
    }
}
