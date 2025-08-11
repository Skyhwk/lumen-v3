<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TranslatorService
{
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiUrl = env('TRANSLATOR_API');
    }

    /**
     * Terjemahkan teks dari bahasa asal ke bahasa target
     *
     * @param string $text     Teks yang akan diterjemahkan
     * @param string $source   Kode bahasa asal (misal 'id' untuk Indonesia)
     * @param string $target   Kode bahasa tujuan (misal 'en' untuk Inggris)
     * @return string|null     Teks hasil terjemahan atau null jika gagal
     */
    public function translate(string $text, string $source, string $target): ?string
    {
        try {
            $response = Http::post($this->apiUrl, [
                'text' => $text,
                'source' => $source,
                'target' => $target,
            ]);

            if ($response->successful()) {
                $response = json_decode($response->body());
                return $response;
            }
        } catch (\Exception $e) {
            \Log::error('Translation API error: ' . $e->getMessage());
        }

        return null;
    }
}
