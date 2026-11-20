<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ringkasan kronologi 5W+1H via Ollama — pola payload/log sama dengan ATS AI matching.
 */
final class InvestigasiKronologiAiService
{
    private const KRONOLOGI_KEYS = [
        'kronologi_ringkasan',
        'kronologi_what',
        'kronologi_who',
        'kronologi_when',
        'kronologi_where',
        'kronologi_why',
        'kronologi_how',
        'kronologi_catatan_khusus',
    ];

    /**
     * @param array<int, int> $bakIds
     * @return array{kronologi: array<string, string>, meta: array<string, mixed>}|null
     */
    public function generate(array $bakIds): ?array
    {
        $bakIds = array_values(array_unique(array_filter(array_map('intval', $bakIds), static fn ($id) => $id > 0)));
        if ($bakIds === []) {
            return null;
        }

        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return null;
        }

        $rows = DB::table('berita_acara_kejadian_disipliner as bak')
            ->whereIn('bak.id', $bakIds)
            ->where('bak.is_active', true)
            ->orderBy('bak.tanggal_kejadian')
            ->orderBy('bak.id')
            ->get([
                'bak.id',
                'bak.nomor_surat',
                'bak.created_by',
                'bak.perihal',
                'bak.uraian_kejadian',
                'bak.tanggal_kejadian',
            ]);

        if ($rows->isEmpty()) {
            return null;
        }

        $bakBlocks = [];
        $structuredBak = [];
        foreach ($rows as $row) {
            $nomor = trim((string) ($row->nomor_surat ?? ''));
            if ($nomor === '') {
                $nomor = 'BAK #' . (int) $row->id;
            }
            $pengaju = trim((string) ($row->created_by ?? '')) ?: 'Tidak diketahui';
            $perihal = trim((string) ($row->perihal ?? '')) ?: '-';
            $uraian = self::plainUraian($row->uraian_kejadian ?? null);
            if ($uraian === '') {
                $uraian = 'Uraian kejadian tidak diisi pada dokumen BAK ini.';
            }

            $structuredBak[] = [
                'bak_id' => (int) $row->id,
                'nomor_surat' => $nomor,
                'pengaju_bak' => $pengaju,
                'perihal' => $perihal,
                'uraian_kejadian' => $uraian,
                'tanggal_kejadian' => $row->tanggal_kejadian,
            ];

            $bakBlocks[] = sprintf(
                "=== BAK: %s ===\nPengaju BAK: %s\nPerihal: %s\nUraian kejadian:\n%s",
                $nomor,
                $pengaju,
                $perihal,
                $uraian
            );
        }

        $promptText = "Anda membantu menyusun ringkasan kronologi investigasi disipliner (form F1) dari beberapa Berita Acara Kejadian (BAK).\n"
            . "Gunakan HANYA fakta dari data BAK berikut. Jangan mengarang detail yang tidak ada.\n"
            . "Bahasa Indonesia, naratif objektif, tanpa opini.\n\n"
            . "Susun:\n"
            . "- kronologi_ringkasan: paragraf naratif yang menggabungkan semua BAK (jika lebih dari satu, jelaskan hubungannya).\n"
            . "- kronologi_what, kronologi_who, kronologi_when, kronologi_where, kronologi_why, kronologi_how: isi 5W+1H sesuai standar investigasi.\n"
            . "- kronologi_catatan_khusus: kosongkan string jika tidak ada catatan khusus; jika ada perbedaan antar BAK, ringkas di sini.\n\n"
            . "Data BAK:\n\n"
            . implode("\n\n", $bakBlocks);

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
                'properties' => array_fill_keys(self::KRONOLOGI_KEYS, ['type' => 'string']),
                'required' => self::KRONOLOGI_KEYS,
                'additionalProperties' => false,
            ],
        ];

        $structuredData = [
            'bak_items' => $structuredBak,
            'ai_payload' => $aiPayload,
        ];

        $this->logAi('info', 'Investigasi kronologi AI request', [
            'bak_ids' => $bakIds,
            'model' => $model,
            'prompt_length' => strlen($promptText),
            'prompt' => $promptText,
        ]);

        $aiResult = $this->sendToOllamaAi($aiPayload);

        $this->logAi('info', 'Investigasi kronologi AI response', [
            'bak_ids' => $bakIds,
            'ai_response' => $this->summarizeAiResponse($aiResult),
        ]);

        if (!$aiResult || empty($aiResult['response'])) {
            return null;
        }

        $parsed = json_decode((string) $aiResult['response'], true);
        if (!is_array($parsed)) {
            return null;
        }

        $kronologi = [];
        foreach (self::KRONOLOGI_KEYS as $key) {
            $kronologi[$key] = trim((string) ($parsed[$key] ?? ''));
        }

        $structuredData['ai_response'] = $this->summarizeAiResponse($aiResult);

        return [
            'kronologi' => $kronologi,
            'meta' => $structuredData,
        ];
    }

    private static function plainUraian(?string $html): string
    {
        $decoded = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', strip_tags($decoded)) ?? '');
    }

    private function logAi(string $level, string $message, array $context = []): void
    {
        Log::channel('investigasi_kronologi_ai')->{$level}($message, $context);
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
            $decoded = json_decode($aiResult['response'], true);
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
                $this->logAi('error', 'Investigasi kronologi sendToOllamaAi cURL error', [
                    'endpoint' => $endpoint,
                    'error' => $curlError,
                    'payload' => $this->summarizeAiPayload($payload),
                ]);
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $decoded = json_decode((string) $response, true);

                return is_array($decoded) ? $decoded : null;
            }

            $this->logAi('warning', 'Investigasi kronologi sendToOllamaAi HTTP error', [
                'endpoint' => $endpoint,
                'http_code' => $httpCode,
                'response' => $response,
                'payload' => $this->summarizeAiPayload($payload),
            ]);

            return null;
        } catch (\Throwable $e) {
            $this->logAi('error', 'Investigasi kronologi sendToOllamaAi exception', [
                'endpoint' => $endpoint,
                'message' => $e->getMessage(),
                'payload' => $this->summarizeAiPayload($payload),
            ]);

            return null;
        }
    }
}