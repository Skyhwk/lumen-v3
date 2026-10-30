<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Menempelkan catatan sistem di bawah garis hitam background halaman dokumen karyawan/SP.
 */
class DokumenPageBackgroundWithNote
{
    private const NOTE_TEXT = 'Note : Dokumen ini diterbitkan otomatis oleh sistem';

    public static function withSystemNote(string $sourcePng, ?string $issuedAt = null): string
    {
        if (!is_file($sourcePng)) {
            return $sourcePng;
        }

        $issuedAt = $issuedAt ?: Carbon::now()->format('YmdGi');
        $image = @imagecreatefrompng($sourcePng);
        $font = self::noteFontPath();

        if (!$image || !$font) {
            if (is_resource($image)) {
                imagedestroy($image);
            }

            return $sourcePng;
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $color = imagecolorallocate($image, 0, 0, 0);
        $scale = imagesx($image) / 210;
        $size = 10.5;
        $right = (int) round(202 * $scale);
        $lineGap = (int) round(2.6 * $scale);
        $borderY = self::shrinkBottomBorderForNote(
            $image,
            self::bottomBorderRow($image),
            $scale,
            $lineGap
        );
        $roomBelow = imagesy($image) - $borderY;
        if ($roomBelow > $lineGap + (int) round(5 * $scale)) {
            $firstBaseline = $borderY + (int) round(5.2 * $scale);
            $secondBaseline = $firstBaseline + $lineGap;
        } else {
            $right = (int) round(196 * $scale);
            $secondBaseline = $borderY - (int) round(1.4 * $scale);
            $firstBaseline = $secondBaseline - $lineGap;
        }

        self::drawRightAlignedText($image, $font, $size, $right, $firstBaseline, $color, self::NOTE_TEXT);
        self::drawRightAlignedText($image, $font, $size, $right, $secondBaseline, $color, $issuedAt);

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'doc-bg-note-' . uniqid('', true) . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    public static function deleteIfTemporary(string $path, string $originalSource): void
    {
        if ($path !== $originalSource && is_file($path)) {
            @unlink($path);
        }
    }

    private static function shrinkBottomBorderForNote($image, int $borderY, float $scale, int $lineGap): int
    {
        $height = imagesy($image);
        $width = imagesx($image);
        // Ruang di bawah garis untuk 2 baris note + jarak aman cetak fisik
        $neededBelow = $lineGap + (int) round(12 * $scale);
        $target = $height - $neededBelow;
        if ($borderY <= $target) {
            return $borderY;
        }
        $left = null;
        $right = null;
        for ($x = 0; $x < $width; $x++) {
            if (self::isBorderPixel($image, $x, $borderY)) {
                if ($left === null) {
                    $left = $x;
                }
                $right = $x;
            }
        }

        if ($left === null || $right === null || $target >= $borderY) {
            return $borderY;
        }

        imagealphablending($image, false);
        $clear = imagecolorallocatealpha($image, 0, 0, 0, 127);
        for ($y = $target; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (self::isBorderPixel($image, $x, $y)) {
                    imagesetpixel($image, $x, $y, $clear);
                }
            }
        }

        imagealphablending($image, true);
        $stroke = imagecolorallocate($image, 17, 17, 17);
        for ($y = $target - 1; $y <= $target; $y++) {
            for ($x = $left; $x <= $right; $x++) {
                imagesetpixel($image, $x, $y, $stroke);
            }
        }

        return $target;
    }

    private static function isBorderPixel($image, int $x, int $y): bool
    {
        $pixel = imagecolorat($image, $x, $y);
        $alpha = ($pixel >> 24) & 0x7F;

        return $alpha < 20
            && (($pixel >> 16) & 0xFF) < 40
            && (($pixel >> 8) & 0xFF) < 40
            && ($pixel & 0xFF) < 40;
    }

    private static function bottomBorderRow($image): int
    {
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = $height - 1; $y > $height - 120; $y--) {
            $dark = 0;
            for ($x = 80; $x < $width - 80; $x += 4) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;
                $red = ($pixel >> 16) & 0xFF;
                if ($alpha < 80 && $red < 80) {
                    $dark++;
                }
            }

            if ($dark > 40) {
                return $y;
            }
        }

        return (int) round(288 * ($width / 210));
    }

    private static function drawRightAlignedText(
        $image,
        string $font,
        float $size,
        int $right,
        int $baseline,
        int $color,
        string $text
    ): void {
        $box = imagettfbbox($size, 0, $font, $text);
        $textWidth = abs($box[2] - $box[0]);
        imagettftext($image, $size, 0, $right - $textWidth, $baseline, $color, $font, $text);
    }

    private static function noteFontPath(): ?string
    {
        $fonts = [
            resource_path('fonts/Calibri/ariali.ttf'),
            'C:\\Windows\\Fonts\\ariali.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Oblique.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf',
        ];

        foreach ($fonts as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return null;
    }
}
