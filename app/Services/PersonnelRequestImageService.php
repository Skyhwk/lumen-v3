<?php

namespace App\Services;

class PersonnelRequestImageService
{
    public function resolveUrl(?string $gambar): ?string
    {
        if ($gambar === null || trim($gambar) === '') {
            return null;
        }

        $gambar = trim($gambar);
        if (str_starts_with($gambar, 'data:image') || filter_var($gambar, FILTER_VALIDATE_URL)) {
            return $gambar;
        }

        $baseUrl = rtrim((string) env('APP_URL_PATH', env('APP_URL', '')), '/');
        if ($baseUrl === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $gambar), '/');

        return $baseUrl . '/' . $path;
    }

    public function appendToJob(object $job): object
    {
        $job->gambar = $job->gambar ?? null;
        $job->image_url = $this->resolveUrl($job->gambar);

        return $job;
    }
}
