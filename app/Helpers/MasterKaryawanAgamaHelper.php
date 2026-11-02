<?php

namespace App\Helpers;

class MasterKaryawanAgamaHelper
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $key = strtolower($raw);
        $map = [
            'islam' => 'Islam',
            'protestan' => 'Kristen Protestan',
            'kristen' => 'Kristen Protestan',
            'kristen protestan' => 'Kristen Protestan',
            'katolik' => 'Katolik',
            'hindu' => 'Hindu',
            'buddha' => 'Buddha',
            'budha' => 'Buddha',
            'konghucu' => 'Khonghucu',
            'khonghucu' => 'Khonghucu',
        ];

        return $map[$key] ?? $raw;
    }
}
