<?php

namespace App\Support;

/**
 * Header PDF modul disipliner: judul + nomor di titik lipatan kedua kertas.
 *
 * Kertas dilipat sekali -> garis tengah (50%). Dilipat sekali lagi -> garis di 75%
 * untuk sisi kanan; titik itulah pusat judul. Logo mengisi separuh kiri.
 */
class DisiplinerPdfHeader
{
    /** Lebar isi halaman A4 dengan margin kiri/kanan 14mm (210 - 28). */
    private const CONTENT_WIDTH_MM = 182;

    /** Hasil lipatan pertama: separuh kertas. */
    private const HALF_WIDTH_MM = self::CONTENT_WIDTH_MM / 2;

    public static function resolveLogoPath(): string
    {
        return is_file(public_path('isl_logo.png'))
            ? public_path('isl_logo.png')
            : public_path('img/isl_logo.png');
    }

    public static function build(
        string $logoPath,
        string $headerTitle,
        string $headerRef,
        bool $withBottomRule = true
    ): string {
        $headerTitleEsc = htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8');
        $headerRefEsc = htmlspecialchars($headerRef, ENT_QUOTES, 'UTF-8');
        $logoEsc = htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8');

        $halfW = self::HALF_WIDTH_MM . 'mm';

        $titleBlock =
            '<div style="font-size:16pt; font-weight:bold; line-height:1.2;">' . $headerTitleEsc . '</div>'
            . '<div style="font-size:10pt; font-weight:bold; margin-top:1.5mm;">' . $headerRefEsc . '</div>';

        $html =
            '<table width="' . self::CONTENT_WIDTH_MM . 'mm" style="font-family:calibri,sans-serif; border-collapse:collapse; table-layout:fixed;">'
            . '<tr><td colspan="2" style="text-align:right; font-size:8pt; color:#666; padding:0 0 1mm;">Page {PAGENO} of {nbpg}</td></tr>'
            . '<tr>'
            . '<td style="width:' . $halfW . '; max-width:' . $halfW . '; vertical-align:middle; padding:0 0 2mm;">'
            . '<img src="' . $logoEsc . '" style="width:48mm; max-width:100%;">'
            . '</td>'
            . '<td style="width:' . $halfW . '; max-width:' . $halfW . '; vertical-align:middle; text-align:center; padding:0 0 2mm;">'
            . $titleBlock
            . '</td>'
            . '</tr>';

        if ($withBottomRule) {
            $html .= '<tr><td colspan="2" style="border-bottom:0.5px solid #888; padding:0; line-height:0;">&nbsp;</td></tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
