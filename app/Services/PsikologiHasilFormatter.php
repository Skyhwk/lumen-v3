<?php

namespace App\Services;

class PsikologiHasilFormatter
{
    public function kategoriLabels(): array
    {
        return config('psikologi.kategori_stress', []);
    }

    public function formatDivisi(?string $divisi): string
    {
        if ($divisi === null || trim($divisi) === '') {
            return '-';
        }

        return preg_match('/^Divisi\s+/i', $divisi)
            ? preg_replace('/^Divisi\s+/i', '', $divisi)
            : $divisi;
    }

    public function formatKesimpulan(?string $text): string
    {
        if ($text === null || trim($text) === '') {
            return '-';
        }

        $text = trim($text);

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_strtolower(mb_substr($text, 1));
    }

    /**
     * Input bebas teks; jika hanya angka dianggap satuan tahun (mis. "13" -> "13 Tahun").
     */
    public function formatMasaKerja($value): string
    {
        if ($value === null) {
            return '-';
        }

        $text = trim((string) $value);
        if ($text === '') {
            return '-';
        }

        if (preg_match('/tahun|bulan|\bth\b|\bbln\b|-/iu', $text)) {
            return $text;
        }

        if (preg_match('/^\d+$/', $text)) {
            return $text . ' Tahun';
        }

        return $text;
    }

    public function buildDetailRows($hasil): array
    {
        if (empty($hasil)) {
            return [];
        }

        $decoded = is_string($hasil) ? json_decode($hasil, true) : (array) $hasil;
        if (!is_array($decoded) || empty($decoded['kesimpulan']) || !is_array($decoded['kesimpulan'])) {
            return [];
        }

        $rows = [];
        foreach ($this->kategoriLabels() as $key => $label) {
            if (!isset($decoded['kesimpulan'][$key]) || !is_array($decoded['kesimpulan'][$key])) {
                continue;
            }

            $detail = $decoded['kesimpulan'][$key];
            $records = isset($detail['records']) && is_array($detail['records']) ? $detail['records'] : [];
            $records = array_values($records);
            while (count($records) < 5) {
                $records[] = '-';
            }

            $rows[] = [
                'kategori' => $label,
                'records' => array_slice($records, 0, 5),
                'total_skor' => $detail['nilai'] ?? '-',
                'kesimpulan' => $this->formatKesimpulan($detail['kesimpulan'] ?? null),
            ];
        }

        return $rows;
    }

    public function buildParticipantDetail(array $participant, ?string $tanggalSampling, ?string $namaPt): array
    {
        return [
            'tanggal_sampling' => $tanggalSampling ?: '-',
            'nama' => $participant['nama'] ?? '-',
            'department' => $this->formatDivisi($participant['divisi'] ?? null),
            'no_sampel' => $participant['no_sampel'] ?? '-',
            'nama_pt' => $namaPt ?: '-',
            'masa_kerja' => $this->formatMasaKerja($participant['lama_kerja'] ?? null),
            'detail_rows' => $this->buildDetailRows($participant['hasil'] ?? null),
        ];
    }
}
