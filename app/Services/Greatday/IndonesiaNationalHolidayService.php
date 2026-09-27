<?php

namespace App\Services\Greatday;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndonesiaNationalHolidayService
{
    /** @return array{items: list<array{tanggal: string, keterangan: string}>, source: ?string} */
    public function forYear(int $year): array
    {
        $year = max(2000, min(2100, $year));
        $cacheKey = 'greatday.national_holidays.' . $year;

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && !empty($cached['items'])) {
            return $cached;
        }

        $providers = [
            fn () => $this->fromTanggalMerah($year),
            fn () => $this->fromApiHariLibur($year),
            fn () => $this->fromDataLiburNasional($year),
        ];

        foreach ($providers as $fetch) {
            try {
                $result = $fetch();
                if (!empty($result['items'])) {
                    Cache::put($cacheKey, $result, Carbon::now()->addHours(12));

                    return $result;
                }
            } catch (\Throwable $e) {
                Log::warning('National holiday provider failed', [
                    'year' => $year,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return ['items' => [], 'source' => null];
    }

    /** @return array{items: list<array{tanggal: string, keterangan: string}>, source: string} */
    private function fromTanggalMerah(int $year): array
    {
        $response = Http::timeout(12)->get('https://tanggalmerah.upset.dev/api/holidays', [
            'year' => $year,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('tanggalmerah HTTP ' . $response->status());
        }

        $body = $response->json();
        $rows = $body['data'] ?? [];
        if (!is_array($rows) || $rows === []) {
            throw new \RuntimeException('tanggalmerah empty payload');
        }

        $items = [];
        foreach ($rows as $row) {
            $date = $this->normalizeDate($row['date'] ?? null);
            $name = trim((string) ($row['name'] ?? ''));
            if ($date === null || $name === '') {
                continue;
            }
            $items[] = ['tanggal' => $date, 'keterangan' => $name];
        }

        return ['items' => $this->dedupeByDate($items), 'source' => 'tanggalmerah.upset.dev'];
    }

    /** @return array{items: list<array{tanggal: string, keterangan: string}>, source: string} */
    private function fromApiHariLibur(int $year): array
    {
        $response = Http::timeout(12)->get('https://api-hari-libur.vercel.app/api', [
            'year' => $year,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('api-hari-libur HTTP ' . $response->status());
        }

        $body = $response->json();
        $rows = $body['data'] ?? [];
        if (!is_array($rows) || $rows === []) {
            throw new \RuntimeException('api-hari-libur empty payload');
        }

        $items = [];
        foreach ($rows as $row) {
            $date = $this->normalizeDate($row['date'] ?? null);
            $name = trim((string) ($row['description'] ?? $row['name'] ?? ''));
            if ($date === null || $name === '') {
                continue;
            }
            $items[] = ['tanggal' => $date, 'keterangan' => $name];
        }

        return ['items' => $this->dedupeByDate($items), 'source' => 'api-hari-libur.vercel.app'];
    }

    /** @return array{items: list<array{tanggal: string, keterangan: string}>, source: string} */
    private function fromDataLiburNasional(int $year): array
    {
        $response = Http::timeout(12)->get('https://data-libur-nasional-indonesia.vercel.app/api/libur', [
            'year' => $year,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('data-libur-nasional HTTP ' . $response->status());
        }

        $body = $response->json();
        $items = [];

        foreach (['libur_nasional', 'cuti_bersama'] as $key) {
            $rows = $body[$key] ?? [];
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $date = $this->normalizeDate($row['date'] ?? null);
                $name = trim((string) ($row['name'] ?? ''));
                if ($date === null || $name === '') {
                    continue;
                }
                $items[] = ['tanggal' => $date, 'keterangan' => $name];
            }
        }

        if ($items === []) {
            throw new \RuntimeException('data-libur-nasional empty payload');
        }

        return ['items' => $this->dedupeByDate($items), 'source' => 'data-libur-nasional-indonesia.vercel.app'];
    }

    private function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param list<array{tanggal: string, keterangan: string}> $items */
    private function dedupeByDate(array $items): array
    {
        $byDate = [];
        foreach ($items as $item) {
            $date = $item['tanggal'];
            if (!isset($byDate[$date])) {
                $byDate[$date] = $item;
                continue;
            }
            if (stripos($byDate[$date]['keterangan'], $item['keterangan']) === false) {
                $byDate[$date]['keterangan'] .= ' / ' . $item['keterangan'];
            }
        }

        $out = array_values($byDate);
        usort($out, fn ($a, $b) => strcmp($a['tanggal'], $b['tanggal']));

        return $out;
    }
}
