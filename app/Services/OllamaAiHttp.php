<?php

namespace App\Services;

/**
 * Header HTTP bersama untuk request ke Ollama (ATS_AI_GENERATE_URL).
 */
class OllamaAiHttp
{
    /**
     * @return list<string>
     */
    public static function jsonRequestHeaders(): array
    {
        $headers = ['Content-Type: application/json'];

        $token = trim((string) env('BEARER_TOKEN', ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }
}