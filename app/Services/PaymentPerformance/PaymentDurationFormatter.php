<?php

namespace App\Services\PaymentPerformance;

class PaymentDurationFormatter
{
    public static function buildSummaryCards($min, $max, $avg): array
    {
        return [
            [
                'title' => 'Waktu Pembayaran Tercepat',
                'subtitle' => 'Rata-rata perusahaan tercepat',
                'value' => self::formatDays($min),
                'accent' => '#16A34A',
                'icon' => '⚡',
            ],
            [
                'title' => 'Waktu Pembayaran Terlama',
                'subtitle' => 'Rata-rata perusahaan terlama',
                'value' => self::formatDays($max),
                'accent' => '#DC2626',
                'icon' => '🐢',
            ],
            [
                'title' => 'Rata-rata Waktu Pembayaran',
                'subtitle' => 'Rata-rata per order',
                'value' => self::formatDays($avg),
                'accent' => '#185ABC',
                'icon' => '📊',
            ],
        ];
    }

    public static function formatDays($days): string
    {
        if ($days === null || $days === '') {
            return '-';
        }

        $days = (float) $days;

        if ($days <= 0) {
            return '-';
        }

        if ($days < 1) {
            return self::formatHours($days * 24);
        }

        $wholeDays = (int) floor($days);
        $remainingHours = ($days - $wholeDays) * 24;

        if ($remainingHours < 0.05) {
            return $wholeDays . ' Hari';
        }

        return $wholeDays . ' Hari ' . self::formatHours($remainingHours);
    }

    private static function formatHours(float $hours): string
    {
        if ($hours < 1) {
            return max(1, (int) round($hours)) . ' Jam';
        }

        if (abs($hours - round($hours)) < 0.05) {
            return (int) round($hours) . ' Jam';
        }

        return number_format($hours, 1, ',', '.') . ' Jam';
    }
}
