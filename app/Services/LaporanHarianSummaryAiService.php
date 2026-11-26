<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Ringkasan laporan harian / mingguan / bulanan via Ollama — pola env/payload/log
 * selaras dengan InvestigasiKronologiAiService.
 */
final class LaporanHarianSummaryAiService
{
    private const SUMMARY_KEYS = [
        'ringkasan_utama',
        'ringkasan_per_sumber',
        'catatan_penting',
    ];

    /**
     * @param string $jenis 'harian'|'mingguan'|'bulanan'
     * @param string $periodeLabel Label periode human-readable (misal "Kamis, 26 November 2026")
     * @param array<int, array<string, mixed>> $blocks Sumber laporan yang sudah terisi
     * @param array<string, mixed> $logContext Konteks tambahan untuk log (karyawan_id, tanggal, dll.)
     * @return array{summary: array<string, mixed>, html: string, meta: array<string, mixed>}|null
     */
    public function generate(
        string $jenis,
        string $periodeLabel,
        array $blocks,
        array $logContext = []
    ): ?array {
        $jenis = in_array($jenis, ['harian', 'mingguan', 'bulanan'], true) ? $jenis : 'harian';

        $sourceBlocks = [];
        $structuredSources = [];
        foreach ($blocks as $index => $block) {
            $nama = trim((string) ($block['nama'] ?? ''));
            if ($nama === '') {
                $nama = ($jenis === 'harian' ? 'Anggota Tim #' : 'Periode #') . ($index + 1);
            }
            $noDokumen = trim((string) ($block['no_dokumen'] ?? ''));
            $tanggal = trim((string) ($block['tanggal'] ?? ''));
            $plainText = self::htmlToReadableText($block['isi'] ?? null);
            if ($plainText === '') {
                continue;
            }

            $structuredSources[] = [
                'sumber_tipe' => $block['sumber_tipe'] ?? null,
                'sumber_id' => $block['sumber_id'] ?? null,
                'karyawan_id' => $block['karyawan_id'] ?? null,
                'nama' => $nama,
                'no_dokumen' => $noDokumen !== '' ? $noDokumen : null,
                'tanggal' => $tanggal !== '' ? $tanggal : null,
                'isi_text' => $plainText,
            ];

            $headerLabel = $jenis === 'harian' ? 'Bawahan' : 'Periode Sumber';
            $metaLine = $noDokumen !== '' ? " (No. Dokumen: {$noDokumen})" : '';
            $sourceBlocks[] = sprintf(
                "=== %s: %s%s ===\n%s",
                $headerLabel,
                $nama,
                $metaLine,
                $plainText
            );
        }

        if ($sourceBlocks === []) {
            return null;
        }

        $promptText = $this->buildPrompt($jenis, $periodeLabel, $sourceBlocks);

        $model = trim((string) env('INVESTIGASI_KRONOLOGI_AI_MODEL', env('ATS_AI_MATCHING_MODEL', 'intilab-ats')));
        if ($model === '') {
            $model = 'intilab-ats';
        }

        $aiPayload = [
            'model' => $model,
            'prompt' => $promptText,
            'stream' => false,
            'format' => [
                'type' => 'object',
                'properties' => [
                    'ringkasan_utama' => ['type' => 'string'],
                    'ringkasan_per_sumber' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'nama' => ['type' => 'string'],
                                'ringkasan' => ['type' => 'string'],
                            ],
                            'required' => ['nama', 'ringkasan'],
                            'additionalProperties' => false,
                        ],
                    ],
                    'catatan_penting' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => self::SUMMARY_KEYS,
                'additionalProperties' => false,
            ],
        ];

        $structuredData = [
            'jenis' => $jenis,
            'periode_label' => $periodeLabel,
            'source_count' => count($structuredSources),
            'sources' => $structuredSources,
            'ai_payload' => $this->summarizeAiPayload($aiPayload),
        ];

        $this->logAi('info', 'Laporan harian summary AI request', array_merge($logContext, [
            'jenis' => $jenis,
            'periode_label' => $periodeLabel,
            'source_count' => count($structuredSources),
            'model' => $model,
            'prompt_length' => strlen($promptText),
            'prompt' => $promptText,
        ]));

        $aiResult = $this->sendToOllamaAi($aiPayload);

        $this->logAi('info', 'Laporan harian summary AI response', array_merge($logContext, [
            'jenis' => $jenis,
            'periode_label' => $periodeLabel,
            'ai_response' => $this->summarizeAiResponse($aiResult),
        ]));

        if (!$aiResult || empty($aiResult['response'])) {
            return null;
        }

        $parsed = json_decode((string) $aiResult['response'], true);
        if (!is_array($parsed)) {
            return null;
        }

        $summary = self::normalizeParsedSummary($parsed, $structuredSources);
        $html = self::renderSummaryHtml($summary, $jenis);
        if ($html === '') {
            return null;
        }

        $structuredData['ai_response'] = $this->summarizeAiResponse($aiResult);

        return [
            'summary' => $summary,
            'html' => $html,
            'meta' => $structuredData,
        ];
    }

    /**
     * @param array<int, string> $sourceBlocks
     */
    private function buildPrompt(string $jenis, string $periodeLabel, array $sourceBlocks): string
    {
        $periodeDisplay = trim($periodeLabel) !== '' ? trim($periodeLabel) : 'periode ini';

        if ($jenis === 'mingguan') {
            $opening = "Anda adalah asisten eksekutif yang membantu atasan merangkum laporan summary harian tim selama satu minggu ({$periodeDisplay}) menjadi summary mingguan yang jernih, terstruktur, dan mudah dipahami.";
            $perSumberRule = '- ringkasan_per_sumber: daftar ringkas untuk setiap periode/hari sumber pada data (field "nama" pakai label periode sumber apa adanya, field "ringkasan" isi 1-2 kalimat inti progres pada hari tersebut).';
            $dataHeader = 'Data Summary Harian:';
        } elseif ($jenis === 'bulanan') {
            $opening = "Anda adalah asisten eksekutif yang membantu atasan merangkum laporan summary mingguan tim selama satu bulan ({$periodeDisplay}) menjadi summary bulanan yang jernih, terstruktur, dan mudah dipahami.";
            $perSumberRule = '- ringkasan_per_sumber: daftar ringkas untuk setiap periode/minggu sumber pada data (field "nama" pakai label periode sumber apa adanya, field "ringkasan" isi 1-2 kalimat inti capaian pada minggu tersebut).';
            $dataHeader = 'Data Summary Mingguan:';
        } else {
            $opening = "Anda adalah asisten eksekutif yang membantu atasan merangkum seluruh laporan harian bawahan pada {$periodeDisplay} sehingga atasan langsung memahami apa saja yang terjadi dan dikerjakan tim sepanjang hari tersebut dalam sekali baca.";
            $perSumberRule = '- ringkasan_per_sumber: WAJIB memuat setiap nama bawahan yang ada di data (field "nama" persis sesuai nama bawahan, field "ringkasan" isi 1-2 kalimat padat dan jelas tentang apa yang dikerjakan/diselesaikan bawahan tersebut hari ini).';
            $dataHeader = 'Data Laporan Harian Bawahan:';
        }

        return $opening . "\n\n"
            . "Aturan ketat:\n"
            . "1. Gunakan HANYA fakta dari data laporan di bawah. Jangan mengarang proyek, angka, status selesai, atau detail yang tidak disebut.\n"
            . "2. Tulis dalam Bahasa Indonesia yang natural, mengalir, ringkas, dan langsung ke inti (tidak bertele-tele dan tidak mengulang-ulang kalimat yang sama).\n"
            . "3. Pertahankan detail kunci yang penting bagi atasan (nama modul/fitur, bug yang diperbaiki, tiket penting, atau hal yang tertunda).\n"
            . "4. JANGAN menulis keterangan hari, tanggal, atau pembuka waktu (seperti \"Pada Senin, 23 November 2026,\", \"Pada tanggal ...\", \"Hari ini,\", \"Selama minggu/bulan ini,\") di awal ringkasan_utama karena periode sudah tercantum di judul halaman. Langsung mulai dari subjek/fokus kegiatan tim.\n\n"
            . "Format isi setiap field JSON:\n"
            . "- ringkasan_utama: 1 paragraf padat (2-3 kalimat saja) yang langsung merangkum fokus utama dan hasil kerja tim tanpa menyebutkan hari/tanggal.\n"
            . $perSumberRule . "\n"
            . "- catatan_penting: maksimal 3 poin singkat HANYA jika ada kendala/hambatan, risiko, atau tindak lanjut yang perlu perhatian atasan (isi array kosong [] jika semua berjalan lancar atau tidak ada yang disebut).\n\n"
            . $dataHeader . "\n\n"
            . implode("\n\n", $sourceBlocks);
    }

    /**
     * @param array<string, mixed> $parsed
     * @param array<int, array<string, mixed>> $structuredSources
     * @return array{
     *   ringkasan_utama: string,
     *   ringkasan_per_sumber: list<array{nama: string, ringkasan: string, karyawan_id: ?int}>,
     *   catatan_penting: list<string>
     * }
     */
    public static function normalizeParsedSummary(array $parsed, array $structuredSources = []): array
    {
        $ringkasanUtama = self::stripLeadingDatePhrase(self::cleanLine($parsed['ringkasan_utama'] ?? ''));

        $catatanPenting = self::normalizeStringList($parsed['catatan_penting'] ?? []);
        if ($catatanPenting === []) {
            $catatanPenting = array_values(array_merge(
                self::normalizeStringList($parsed['kendala_dan_perhatian'] ?? []),
                self::normalizeStringList($parsed['tindak_lanjut'] ?? [])
            ));
        }

        $sourceIdByName = [];
        foreach ($structuredSources as $src) {
            $srcName = self::normalizeLookupKey((string) ($src['nama'] ?? ''));
            $srcKaryawanId = isset($src['karyawan_id']) ? (int) $src['karyawan_id'] : 0;
            if ($srcName !== '' && $srcKaryawanId > 0) {
                $sourceIdByName[$srcName] = $srcKaryawanId;
            }
        }

        $perSumber = [];
        if (is_array($parsed['ringkasan_per_sumber'] ?? null)) {
            foreach ($parsed['ringkasan_per_sumber'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $nama = self::cleanLine($item['nama'] ?? '');
                $ringkasan = self::cleanLine($item['ringkasan'] ?? '');
                if ($nama === '' && $ringkasan === '') {
                    continue;
                }
                $karyawanId = isset($item['karyawan_id']) && (int) $item['karyawan_id'] > 0
                    ? (int) $item['karyawan_id']
                    : null;
                if ($karyawanId === null && $nama !== '') {
                    $key = self::normalizeLookupKey($nama);
                    $karyawanId = $sourceIdByName[$key] ?? null;
                }
                $perSumber[] = [
                    'nama' => $nama !== '' ? $nama : '-',
                    'ringkasan' => $ringkasan !== '' ? $ringkasan : '-',
                    'karyawan_id' => $karyawanId,
                ];
            }
        }

        return [
            'ringkasan_utama' => $ringkasanUtama,
            'ringkasan_per_sumber' => $perSumber,
            'catatan_penting' => $catatanPenting,
        ];
    }

    /**
     * Ubah hasil JSON terstruktur dari AI menjadi HTML ringkas dan mudah dipindai
     * sekaligus aman untuk dirangkum ulang di level mingguan/bulanan.
     *
     * @param array{
     *   ringkasan_utama: string,
     *   ringkasan_per_sumber: list<array{nama: string, ringkasan: string, karyawan_id?: ?int}>,
     *   catatan_penting: list<string>
     * } $summary
     */
    public static function renderSummaryHtml(array $summary, string $jenis = 'harian'): string
    {
        $parts = [];

        $ringkasanUtama = trim((string) ($summary['ringkasan_utama'] ?? ''));
        if ($ringkasanUtama !== '') {
            $parts[] = '<p class="lh-ai-lead">' . self::escape($ringkasanUtama) . '</p>';
        }

        $perSumber = $summary['ringkasan_per_sumber'] ?? [];
        if ($perSumber !== []) {
            $items = array_map(
                static function (array $row): string {
                    $karyawanId = isset($row['karyawan_id']) ? (int) $row['karyawan_id'] : 0;
                    $dataAttr = $karyawanId > 0 ? ' data-karyawan-id="' . $karyawanId . '"' : '';

                    return '<div class="lh-ai-member"' . $dataAttr . '>'
                        . '<div class="lh-ai-member-name">' . self::escape($row['nama']) . '</div>'
                        . '<div class="lh-ai-member-text">' . self::escape($row['ringkasan']) . '</div>'
                        . '</div>';
                },
                $perSumber
            );
            $parts[] = '<div class="lh-ai-members">' . implode('', $items) . '</div>';
        }

        $catatan = $summary['catatan_penting'] ?? [];
        if ($catatan !== []) {
            $items = array_map(
                static fn (string $line) => '<li>' . self::escape($line) . '</li>',
                $catatan
            );
            $parts[] = '<div class="lh-ai-notes">'
                . '<div class="lh-ai-notes-title">Perlu Diperhatikan &amp; Tindak Lanjut</div>'
                . '<ul>' . implode('', $items) . '</ul>'
                . '</div>';
        }

        if ($parts === []) {
            return '';
        }

        return '<div class="lh-ai-summary">' . implode('', $parts) . '</div>';
    }

    /**
     * Ekstrak ringkasan per bawahan / per periode dari HTML summary yang sudah tersimpan
     * agar bisa ditampilkan saat user membuka detail laporan individu.
     *
     * @return array{by_karyawan_id: array<int, string>, by_name: array<string, string>}
     */
    public static function extractSourceSummaries(?string $summaryHtml): array
    {
        $byKaryawanId = [];
        $byName = [];

        $html = trim((string) $summaryHtml);
        if ($html === '') {
            return ['by_karyawan_id' => $byKaryawanId, 'by_name' => $byName];
        }

        // Format baru: <div class="lh-ai-member" data-karyawan-id="..."><div class="lh-ai-member-name">...</div><div class="lh-ai-member-text">...</div></div>
        if (preg_match_all(
            '/<div\s+class="lh-ai-member"(?:\s+data-karyawan-id="(\d+)")?[^>]*>\s*<div\s+class="lh-ai-member-name">(.*?)<\/div>\s*<div\s+class="lh-ai-member-text">(.*?)<\/div>\s*<\/div>/is',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $kid = (int) ($m[1] ?? 0);
                $name = self::cleanLine(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $text = self::cleanLine(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text === '') {
                    continue;
                }
                if ($kid > 0) {
                    $byKaryawanId[$kid] = $text;
                }
                $key = self::normalizeLookupKey($name);
                if ($key !== '') {
                    $byName[$key] = $text;
                }
            }
        }

        // Fallback format lama: <li><strong>Nama:</strong> Ringkasan</li>
        if ($byName === [] && preg_match_all(
            '/<li>\s*<strong>(.*?)<\/strong>\s*(.*?)<\/li>/is',
            $html,
            $legacyMatches,
            PREG_SET_ORDER
        )) {
            foreach ($legacyMatches as $m) {
                $name = self::cleanLine(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $name = rtrim($name, ': ');
                $text = self::cleanLine(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $key = self::normalizeLookupKey($name);
                if ($key !== '' && $text !== '') {
                    $byName[$key] = $text;
                }
            }
        }

        return [
            'by_karyawan_id' => $byKaryawanId,
            'by_name' => $byName,
        ];
    }

    /**
     * @param array{by_karyawan_id: array<int, string>, by_name: array<string, string>} $extracted
     */
    public static function resolveSourceSummary(array $extracted, ?int $karyawanId, string $nama, bool $useKaryawanId = true): ?string
    {
        if ($useKaryawanId && $karyawanId !== null && $karyawanId > 0 && isset($extracted['by_karyawan_id'][$karyawanId])) {
            return $extracted['by_karyawan_id'][$karyawanId];
        }

        $key = self::normalizeLookupKey($nama);
        if ($key !== '' && isset($extracted['by_name'][$key])) {
            return $extracted['by_name'][$key];
        }

        return null;
    }

    private static function normalizeLookupKey(string $value): string
    {
        $clean = self::cleanLine($value);

        return $clean !== '' ? mb_strtolower($clean, 'UTF-8') : '';
    }

    /**
     * Potong frasa pembuka tanggal/periode di awal paragraf ringkasan utama
     * (misal "Pada Senin, 23 November 2026, tim..." -> "Tim...").
     */
    public static function stripLeadingDatePhrase(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $months = '(?:Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember|Jan|Feb|Mar|Apr|Mei|Jun|Jul|Agu|Ags|Sep|Okt|Nov|Des)';
        $days = '(?:Senin|Selasa|Rabu|Kamis|Jumat|Jum\'at|Sabtu|Minggu)';

        $patterns = [
            // "Pada (hari) Senin, 23 November 2026, ..." atau "Senin, 23 November 2026, ..."
            '/^(?:(?:Pada|Di|Untuk|Sepanjang|Selama)\s+)?(?:hari\s+)?' . $days . '\s*,?\s+\d{1,2}\s+' . $months . '\s+\d{4}\s*(?:,|:|-|–)?\s*/iu',
            // "Pada periode 23 Nov – 29 Nov 2026, ..."
            '/^(?:(?:Pada|Di|Untuk|Sepanjang|Selama)\s+)?(?:periode\s+)?\d{1,2}\s+' . $months . '(?:\s+\d{4})?\s*(?:-|–|s\.?\/?d\.?|hingga|sampai)\s*\d{1,2}\s+' . $months . '\s+\d{4}\s*(?:,|:|-|–)?\s*/iu',
            // "Pada (tanggal) 23 November 2026, ..."
            '/^(?:(?:Pada|Di|Untuk|Sepanjang|Selama)\s+)?(?:tanggal\s+)?\d{1,2}\s+' . $months . '\s+\d{4}\s*(?:,|:|-|–)?\s*/iu',
            // "Selama/Pada bulan November 2026, ..."
            '/^(?:(?:Pada|Di|Untuk|Sepanjang|Selama)\s+)?(?:bulan\s+)?' . $months . '\s+\d{4}\s*(?:,|:|-|–)?\s*/iu',
            // "Pada hari ini, ..." / "Sepanjang minggu ini, ..." / "Hari ini, ..."
            '/^(?:(?:Pada|Di|Untuk|Sepanjang|Selama)\s+)?(?:hari|minggu|bulan|periode)\s+ini\s*(?:,|:|-|–)\s*/iu',
        ];

        $result = $text;
        foreach ($patterns as $pattern) {
            $stripped = preg_replace($pattern, '', $result, 1);
            if ($stripped !== null && $stripped !== $result && trim($stripped) !== '') {
                $result = ltrim($stripped);
                break;
            }
        }

        if ($result === '') {
            return $text;
        }

        $first = mb_substr($result, 0, 1, 'UTF-8');
        $rest = mb_substr($result, 1, null, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8') . $rest;
    }

    /**
     * Konversi HTML TinyMCE ke teks polos sambil mempertahankan pemisah baris/bullet
     * agar struktur poin laporan bawahan terbaca jelas oleh AI.
     */
    public static function htmlToReadableText(?string $html): string
    {
        $raw = (string) $html;
        if (trim($raw) === '') {
            return '';
        }

        $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $raw) ?? $raw;
        $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $raw) ?? $raw;
        $raw = preg_replace('/<li\b[^>]*>/i', "\n- ", $raw) ?? $raw;
        $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw) ?? $raw;
        $raw = preg_replace('/<\/(?:p|div|li|tr|h[1-6]|section|ul|ol)>/i', "\n", $raw) ?? $raw;

        $decoded = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\xc2\xa0", ' ', $decoded);

        $lines = preg_split('/\R/u', $decoded) ?: [];
        $cleaned = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t]+/u', ' ', $line) ?? '');
            if ($line !== '' && $line !== '-') {
                $cleaned[] = $line;
            }
        }

        return implode("\n", $cleaned);
    }

    /**
     * @param mixed $list
     * @return list<string>
     */
    private static function normalizeStringList($list): array
    {
        if (!is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $item) {
            $text = self::cleanLine($item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /** @param mixed $value */
    private static function cleanLine($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function logAi(string $level, string $message, array $context = []): void
    {
        Log::channel('laporan_harian_summary_ai')->{$level}($message, $context);
    }

    private function summarizeAiPayload(array $payload): array
    {
        $prompt = (string) ($payload['prompt'] ?? '');

        return [
            'model' => $payload['model'] ?? null,
            'stream' => $payload['stream'] ?? null,
            'format' => $payload['format'] ?? null,
            'prompt_length' => strlen($prompt),
        ];
    }

    private function summarizeAiResponse(?array $aiResult): ?array
    {
        if (!$aiResult) {
            return null;
        }

        $parsedResponse = null;
        if (!empty($aiResult['response'])) {
            $decoded = json_decode((string) $aiResult['response'], true);
            $parsedResponse = is_array($decoded) ? $decoded : $aiResult['response'];
        }

        return [
            'model' => $aiResult['model'] ?? null,
            'created_at' => $aiResult['created_at'] ?? null,
            'done' => $aiResult['done'] ?? null,
            'done_reason' => $aiResult['done_reason'] ?? null,
            'response' => $parsedResponse,
            'prompt_eval_count' => $aiResult['prompt_eval_count'] ?? null,
            'eval_count' => $aiResult['eval_count'] ?? null,
            'total_duration' => $aiResult['total_duration'] ?? null,
        ];
    }

    private function sendToOllamaAi(array $payload): ?array
    {
        $endpoint = rtrim((string) env('ATS_AI_GENERATE_URL', 'http://10.88.209.240:11434/api/generate'), '/');

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, OllamaAiHttp::jsonRequestHeaders());
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                $this->logAi('error', 'Laporan harian summary sendToOllamaAi cURL error', [
                    'endpoint' => $endpoint,
                    'error' => $curlError,
                    'payload' => $this->summarizeAiPayload($payload),
                ]);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode((string) $response, true);

                return is_array($decoded) ? $decoded : null;
            }

            $this->logAi('warning', 'Laporan harian summary sendToOllamaAi HTTP error', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response,
                'payload' => $this->summarizeAiPayload($payload),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logAi('error', 'Laporan harian summary sendToOllamaAi exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'payload' => $this->summarizeAiPayload($payload),
            ]);

            return null;
        }
    }
}
