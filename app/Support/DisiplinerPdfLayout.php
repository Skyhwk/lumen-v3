<?php

namespace App\Support;

/**
 * Margin & page-break PDF dokumen disipliner (A4 + background note + footer alamat).
 */
class DisiplinerPdfLayout
{
    /**
     * Ruang bawah cukup untuk footer HTML, catatan sistem di watermark, dan margin cetak fisik.
     *
     * @return array<string, mixed>
     */
    public static function mpdfPageConfig(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 34,
            'margin_bottom' => 40,
            'margin_header' => 6,
            'margin_footer' => 18,
            'setAutoBottomMargin' => 'stretch',
        ], $overrides);
    }
}
