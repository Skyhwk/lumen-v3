<?php

namespace App\Services;

class InternalTypingTestService
{
    public const DEFAULT_WORD_POOL_SIZE = 120;
    public const DEFAULT_DURATION_SECONDS = 60;
    public const TYPING_CATEGORY_NAMES = ['KECEPATAN MENGETIK', 'TES MENGETIK', 'TYPING TEST'];

    public static function isTypingCategoryName(?string $name): bool
    {
        $normalized = strtoupper(trim((string) $name));

        return in_array($normalized, self::TYPING_CATEGORY_NAMES, true);
    }

    public function buildQuestions(array $definition = []): array
    {
        $poolSize = max(20, (int) ($definition['question_count'] ?? self::DEFAULT_WORD_POOL_SIZE));
        $durationMinutes = max(1, (int) ($definition['duration_minutes'] ?? 1));
        $hasLimit = array_key_exists('has_time_limit', $definition)
            ? (bool) $definition['has_time_limit']
            : true;
        $durationSeconds = $hasLimit
            ? ($durationMinutes > 0 ? $durationMinutes * 60 : self::DEFAULT_DURATION_SECONDS)
            : self::DEFAULT_DURATION_SECONDS;

        $words = $this->pickWords($poolSize);

        return [[
            'id' => 'typing-' . bin2hex(random_bytes(8)),
            'source' => 'typing_test',
            'order' => 1,
            'type' => 'typing_test',
            'text' => 'Ketik kata-kata di bawah secepat dan setepat mungkin.',
            'words' => $words,
            'word_count' => count($words),
            'duration_seconds' => $durationSeconds,
            'image' => [],
            'options' => [],
        ]];
    }

    public function normalizeAnswerPayload($answer, array $question, bool $allowEmptyWords = false): ?array
    {
        if (!is_array($answer)) {
            return null;
        }

        $typed = $answer['typed_words'] ?? null;
        if (!is_array($typed)) {
            return null;
        }

        if (!$allowEmptyWords && count($typed) === 0) {
            return null;
        }

        $typed = array_values(array_filter(array_map(function ($word) {
            return $this->normalizeWord($word);
        }, $typed), function ($word) {
            return $word !== '';
        }));

        $limitMs = (int) ($answer['duration_limit_ms'] ?? 0);
        if ($limitMs <= 0) {
            $limitMs = (int) ($question['duration_seconds'] ?? self::DEFAULT_DURATION_SECONDS) * 1000;
        }

        $durationMs = $this->resolveDurationMs(
            (int) ($answer['duration_ms'] ?? 0),
            $limitMs,
            false
        );

        $normalized = [
            'typed_words' => $typed,
            'duration_ms' => $durationMs,
            'duration_limit_ms' => $limitMs,
            'target_word_count' => count($question['words'] ?? []),
        ];

        if (!empty($answer['auto_timeout'])) {
            $normalized['auto_timeout'] = true;
        }
        if (!empty($answer['auto_expired'])) {
            $normalized['auto_expired'] = true;
        }

        return $normalized;
    }

    public function resolveDurationMs(int $durationMs, int $limitMs, bool $fullSession = false): int
    {
        if ($limitMs <= 0) {
            $limitMs = self::DEFAULT_DURATION_SECONDS * 1000;
        }

        if ($durationMs <= 0) {
            return $limitMs;
        }

        if ($durationMs < 500) {
            $durationMs = 500;
        }

        return min($durationMs, $limitMs);
    }

    public function scoreSession(array $questions, array $answers): array
    {
        $question = $questions[0] ?? [];
        $targets = $question['words'] ?? [];
        $payload = null;

        foreach ($answers as $value) {
            if (is_array($value) && isset($value['typed_words'])) {
                $payload = $value;
                break;
            }
        }

        $typed = is_array($payload) ? ($payload['typed_words'] ?? []) : [];
        $limitMs = is_array($payload) ? (int) ($payload['duration_limit_ms'] ?? 0) : 0;
        if ($limitMs <= 0) {
            $limitMs = (int) ($question['duration_seconds'] ?? self::DEFAULT_DURATION_SECONDS) * 1000;
        }

        $durationMs = is_array($payload)
            ? $this->resolveDurationMs(
                (int) ($payload['duration_ms'] ?? 0),
                $limitMs,
                false
            )
            : $limitMs;

        $correct = 0;
        $compared = min(count($typed), count($targets));
        for ($i = 0; $i < $compared; $i++) {
            if ($this->normalizeWord($typed[$i]) === $this->normalizeWord($targets[$i])) {
                $correct++;
            }
        }

        $typedCount = count($typed);
        $accuracy = $typedCount > 0 ? round(($correct / $typedCount) * 100, 2) : 0;
        $minutes = max($durationMs / 60000, 1 / 60);
        $charCount = array_sum(array_map('strlen', $typed));
        $wpm = round(($charCount / 5) / $minutes, 1);

        $incorrect = max(0, $typedCount - $correct);

        return [
            'engine' => 'typing_test',
            'answered' => $typedCount,
            'total_questions' => 1,
            'correct_words' => $correct,
            'incorrect_words' => $incorrect,
            'typed_words' => $typedCount,
            'target_words' => count($targets),
            'accuracy_percent' => $accuracy,
            'wpm' => $wpm,
            'duration_ms' => $durationMs,
            'duration_limit_seconds' => (int) round($limitMs / 1000),
            'score' => $accuracy,
        ];
    }

    private function pickWords(int $count): array
    {
        $pool = $this->wordPool();
        shuffle($pool);

        if (count($pool) >= $count) {
            return array_slice($pool, 0, $count);
        }

        $words = [];
        while (count($words) < $count) {
            $words = array_merge($words, $pool);
        }

        return array_slice($words, 0, $count);
    }

    private function wordPool(): array
    {
        $path = base_path('resources/data/internal_typing_words_id.json');
        if (is_readable($path)) {
            $decoded = json_decode(file_get_contents($path), true);
            if (is_array($decoded) && !empty($decoded['words'])) {
                return array_values(array_filter(array_map('strval', $decoded['words'])));
            }
        }

        return [
            'saya', 'anda', 'kami', 'mereka', 'bisa', 'harus', 'sudah', 'belum', 'baik', 'buruk',
            'kerja', 'tim', 'data', 'sistem', 'proses', 'hasil', 'target', 'waktu', 'cepat', 'tepat',
            'lab', 'sample', 'uji', 'mutu', 'standar', 'dokumen', 'laporan', 'klien', 'internal', 'eksternal',
            'integritas', 'disiplin', 'fokus', 'teliti', 'rapi', 'aman', 'bersih', 'jujur', 'tanggap', 'aktif',
            'portal', 'assessment', 'karyawan', 'manajer', 'departemen', 'proyek', 'jadwal', 'meeting', 'email', 'notifikasi',
        ];
    }

    private function normalizeWord($value): string
    {
        $word = mb_strtolower(trim((string) $value), 'UTF-8');

        return preg_replace('/[^\p{L}\p{N}\-]/u', '', $word) ?? '';
    }
}
