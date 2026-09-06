<?php

namespace App\Services;

/**
 * Classic DISC profile graphs (Graph III / I / II) from disc_rules scores.
 * Dots are plotted on the -scale..+scale axis already stored on each profile.
 */
class DiscProfileChartRenderer
{
    private const BLUE = [47, 95, 154];
    private const BLUE_SOFT = [214, 230, 245];
    private const GRID = [184, 201, 220];
    private const AXIS = [90, 118, 150];
    private const INK = [20, 20, 20];
    private const WHITE = [255, 255, 255];
    private const BAND = [198, 220, 240];

    public function renderToFile(array $discDetail): ?string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $scale = max(1, (float) ($discDetail['score_scale'] ?? 8));
        $profiles = [];
        foreach ($discDetail['profiles'] ?? [] as $profile) {
            $line = (int) ($profile['line'] ?? 0);
            if ($line < 1 || $line > 3 || empty($profile['scores'])) {
                continue;
            }
            $profiles[$line] = $profile;
        }

        if ($profiles === []) {
            return null;
        }

        $width = 1680;
        $height = 780;
        $im = imagecreatetruecolor($width, $height);
        if ($im === false) {
            return null;
        }

        if (function_exists('imageantialias')) {
            imageantialias($im, true);
        }
        $c = $this->palette($im);
        imagefilledrectangle($im, 0, 0, $width, $height, $c['white']);

        $gap = 18;
        $left = 16;
        $usable = $width - 32;
        $w3 = (int) round($usable * 0.40);
        $w1 = (int) round($usable * 0.30);
        $w2 = $usable - $w3 - $w1 - ($gap * 2);

        $panels = [
            ['line' => 3, 'x' => $left, 'w' => $w3, 'title' => 'Grafik 3 (Change)', 'caption' => 'Kepribadian kerja yang lebih menetap'],
            ['line' => 1, 'x' => $left + $w3 + $gap, 'w' => $w1, 'title' => 'Grafik 1 (Most)', 'caption' => 'Saat tampil di muka umum / wawancara'],
            ['line' => 2, 'x' => $left + $w3 + $w1 + ($gap * 2), 'w' => $w2, 'title' => 'Grafik 2 (Least)', 'caption' => 'Saat mendapat tekanan'],
        ];

        foreach ($panels as $panel) {
            $this->drawPanel(
                $im,
                $c,
                $panel['x'],
                12,
                $panel['w'],
                $height - 24,
                $profiles[$panel['line']] ?? null,
                $scale,
                $panel['title'],
                $panel['caption']
            );
        }

        $path = sys_get_temp_dir() . '/disc_profile_' . uniqid('', true) . '.png';
        imagepng($im, $path, 6);
        imagedestroy($im);

        return is_file($path) ? $path : null;
    }

    private function drawPanel(
        $im,
        array $c,
        int $x,
        int $y,
        int $w,
        int $h,
        ?array $profile,
        float $scale,
        string $title,
        string $caption
    ): void {
        $headerH = 34;
        $badgeH = 36;
        $footerH = 44;
        $plotTop = $y + $headerH + $badgeH;
        $plotBottom = $y + $h - $footerH;
        $plotH = max(80, $plotBottom - $plotTop);

        imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $c['white']);
        imagerectangle($im, $x, $y, $x + $w, $y + $h, $c['blue']);

        imagefilledrectangle($im, $x, $y, $x + $w, $y + $headerH, $c['blue']);
        $pattern = trim((string) ($profile['pattern'] ?? ''));
        if ($pattern === '' || strcasecmp($pattern, 'Pattern tidak tersedia') === 0) {
            $pattern = '';
        }
        $this->text($im, $c['white'], $x + 10, $y + 8, $title, 13, true);
        if ($pattern !== '') {
            $this->text($im, $c['white'], $x + $w - 10, $y + 10, strtoupper($pattern), 9, true, 'right');
        }

        $leftGutter = 36;
        $rightGutter = 28;
        $plotX = $x + $leftGutter;
        $plotW = $w - $leftGutter - $rightGutter;
        $colW = $plotW / 4.0;
        $keys = ['D', 'I', 'S', 'C'];

        for ($i = 0; $i < 4; $i++) {
            $cx = (int) round($plotX + ($i + 0.5) * $colW);
            $this->drawShield($im, $c, $cx, $y + $headerH + 18, $keys[$i]);
        }

        $midTop = $this->valueToY(1.15, $scale, $plotTop, $plotH);
        $midBottom = $this->valueToY(-1.15, $scale, $plotTop, $plotH);
        imagefilledrectangle($im, $plotX, $midTop, $plotX + $plotW, $midBottom, $c['band']);

        for ($seg = 0; $seg <= 7; $seg++) {
            $yy = (int) round($plotTop + ($seg / 7) * $plotH);
            imageline($im, $plotX, $yy, $plotX + $plotW, $yy, $seg === 0 || $seg === 7 ? $c['blue'] : $c['grid']);
        }

        for ($i = 0; $i < 4; $i++) {
            $cx = (int) round($plotX + ($i + 0.5) * $colW);
            imageline($im, $cx, $plotTop, $cx, $plotBottom, $c['axis']);
        }

        $this->text($im, $c['axis'], $x + 6, $plotTop - 2, 'INTENSITAS', 7, false);
        $this->text($im, $c['axis'], $x + $w - 6, $plotTop - 2, 'SEGMEN', 7, false, 'right');

        $intensityMarks = [8, 6, 4, 2, 0, -2, -4, -6, -8];
        foreach ($intensityMarks as $mark) {
            if (abs($mark) > $scale) {
                continue;
            }
            $yy = $this->valueToY($mark, $scale, $plotTop, $plotH);
            $label = $mark > 0 ? '+' . $mark : (string) $mark;
            $this->text($im, $c['ink'], $x + 6, $yy - 6, $label, 8, false);
        }

        for ($seg = 7; $seg >= 1; $seg--) {
            $centerVal = (($seg - 4) * (2 * $scale / 7));
            $yy = $this->valueToY($centerVal, $scale, $plotTop, $plotH);
            $this->text($im, $seg === 4 ? $c['blue'] : $c['ink'], $x + $w - 8, $yy - 6, (string) $seg, 9, $seg === 4, 'right');
        }

        $points = [];
        $scoresByKey = [];
        foreach ($profile['scores'] ?? [] as $score) {
            $scoresByKey[strtoupper((string) ($score['key'] ?? ''))] = (float) ($score['value'] ?? 0);
        }

        foreach ($keys as $i => $key) {
            $value = $scoresByKey[$key] ?? 0.0;
            $px = (int) round($plotX + ($i + 0.5) * $colW);
            $py = $this->valueToY($value, $scale, $plotTop, $plotH);
            $points[] = [$px, $py];
        }

        if (function_exists('imageantialias')) {
            imageantialias($im, false);
        }
        imagesetthickness($im, 4);
        for ($i = 0; $i < 3; $i++) {
            imageline($im, $points[$i][0], $points[$i][1], $points[$i + 1][0], $points[$i + 1][1], $c['ink']);
        }
        imagesetthickness($im, 1);

        foreach ($points as $point) {
            imagefilledellipse($im, $point[0], $point[1], 16, 16, $c['ink']);
            imageellipse($im, $point[0], $point[1], 16, 16, $c['white']);
        }

        imagefilledrectangle($im, $x, $y + $h - $footerH, $x + $w, $y + $h, $c['blue']);
        $this->text($im, $c['white'], $x + (int) ($w / 2), $y + $h - 30, $caption, 9, false, 'center');
    }

    private function valueToY(float $value, float $scale, int $plotTop, int $plotH): int
    {
        $clamped = max(-$scale, min($scale, $value));
        $ratio = ($scale - $clamped) / (2 * $scale);
        $pad = 10;

        return (int) round($plotTop + $pad + ($ratio * ($plotH - ($pad * 2))));
    }

    private function drawShield($im, array $c, int $cx, int $cy, string $letter): void
    {
        $pts = [
            $cx - 14, $cy - 12,
            $cx + 14, $cy - 12,
            $cx + 14, $cy + 4,
            $cx, $cy + 14,
            $cx - 14, $cy + 4,
        ];
        imagefilledpolygon($im, $pts, 5, $c['blue']);
        $this->text($im, $c['white'], $cx, $cy - 10, $letter, 11, true, 'center');
    }

    private function palette($im): array
    {
        return [
            'blue' => imagecolorallocate($im, self::BLUE[0], self::BLUE[1], self::BLUE[2]),
            'blueSoft' => imagecolorallocate($im, self::BLUE_SOFT[0], self::BLUE_SOFT[1], self::BLUE_SOFT[2]),
            'grid' => imagecolorallocate($im, self::GRID[0], self::GRID[1], self::GRID[2]),
            'axis' => imagecolorallocate($im, self::AXIS[0], self::AXIS[1], self::AXIS[2]),
            'ink' => imagecolorallocate($im, self::INK[0], self::INK[1], self::INK[2]),
            'white' => imagecolorallocate($im, self::WHITE[0], self::WHITE[1], self::WHITE[2]),
            'band' => imagecolorallocate($im, self::BAND[0], self::BAND[1], self::BAND[2]),
        ];
    }

    private function text($im, int $color, int $x, int $y, string $text, int $size, bool $bold = false, string $align = 'left'): void
    {
        $font = $this->fontPath($bold);
        if ($font) {
            $box = imagettfbbox($size, 0, $font, $text);
            $tw = abs($box[2] - $box[0]);
            if ($align === 'right') {
                $x -= $tw;
            } elseif ($align === 'center') {
                $x -= (int) round($tw / 2);
            }
            imagettftext($im, $size, 0, $x, $y + $size, $color, $font, $text);
            return;
        }

        $fontId = $bold ? 5 : 3;
        $tw = imagefontwidth($fontId) * strlen($text);
        if ($align === 'right') {
            $x -= $tw;
        } elseif ($align === 'center') {
            $x -= (int) round($tw / 2);
        }
        imagestring($im, $fontId, $x, $y, $text, $color);
    }

    private function fontPath(bool $bold): ?string
    {
        $candidates = $bold
            ? [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
                '/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
            ]
            : [
                '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
                '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
                '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
