<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SamplerTrackingPicContacts
{
    public static function payload($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return ['pic' => [], 'dokumentasi' => []];
        }

        $isWrapped = array_key_exists('pic', $raw) || array_key_exists('dokumentasi', $raw);

        return [
            'pic' => self::normalize($isWrapped ? ($raw['pic'] ?? []) : $raw),
            'dokumentasi' => self::normalizeDokumentasi($isWrapped ? ($raw['dokumentasi'] ?? []) : []),
        ];
    }

    public static function normalize($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        $contacts = [];
        foreach ($raw as $item) {
            $item = is_object($item) ? (array) $item : $item;
            if (!is_array($item)) {
                continue;
            }

            $nama = trim((string) ($item['nama'] ?? ''));
            $jabatan = trim((string) ($item['jabatan'] ?? ''));
            $telepon = preg_replace('/\D+/', '', (string) ($item['telepon'] ?? ''));

            if ($nama === '' && $jabatan === '' && $telepon === '') {
                continue;
            }

            if ($nama === '' || $jabatan === '' || $telepon === '') {
                throw new \InvalidArgumentException('Setiap PIC wajib diisi nama, jabatan, dan nomor telepon.');
            }

            $contacts[] = [
                'nama' => $nama,
                'jabatan' => $jabatan,
                'telepon' => $telepon,
            ];
        }

        return array_values($contacts);
    }

    public static function normalizeDokumentasi($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = json_last_error() === JSON_ERROR_NONE ? $decoded : [$raw];
        }

        if (!is_array($raw)) {
            return [];
        }

        $docs = [];
        foreach ($raw as $item) {
            if (is_object($item)) {
                $item = (array) $item;
            }
            if (is_array($item)) {
                $item = $item['path'] ?? $item['url'] ?? $item['photo'] ?? $item['src'] ?? '';
            }

            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }

            $docs[] = $item;
        }

        return array_values($docs);
    }

    public static function requireFilled($raw): array
    {
        try {
            $payload = self::payload($raw);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'pic_contacts' => [$exception->getMessage()],
            ]);
        }

        if ($payload['pic'] === []) {
            throw ValidationException::withMessages([
                'pic_contacts' => ['Minimal isi 1 PIC yang ditemui di lokasi.'],
            ]);
        }

        return $payload;
    }

    public static function isFilled($raw): bool
    {
        try {
            return self::payload($raw)['pic'] !== [];
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public static function fromEvent($event): array
    {
        if (!$event) {
            return ['pic' => [], 'dokumentasi' => []];
        }

        try {
            return self::payload($event->pic_contacts ?? null);
        } catch (\Throwable $exception) {
            return ['pic' => [], 'dokumentasi' => []];
        }
    }
}
