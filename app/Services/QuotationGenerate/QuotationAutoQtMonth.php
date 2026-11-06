<?php

namespace App\Services\QuotationGenerate;

use Carbon\Carbon;
use InvalidArgumentException;

class QuotationAutoQtMonth
{
    private const MONTH_ALIASES = [
        'januari' => 1, 'january' => 1, 'jan' => 1,
        'februari' => 2, 'february' => 2, 'feb' => 2,
        'maret' => 3, 'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'mei' => 5, 'may' => 5,
        'juni' => 6, 'june' => 6, 'jun' => 6,
        'juli' => 7, 'july' => 7, 'jul' => 7,
        'agustus' => 8, 'august' => 8, 'agu' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'desember' => 12, 'december' => 12, 'des' => 12, 'dec' => 12,
    ];

    private const MONTH_NAMES = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    private const MONTH_SHORT = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    private const PROMO_CODE_MAX = 20;

    /** @var Carbon */
    private $targetStart;

    /** @var Carbon */
    private $orderFrom;

    /** @var Carbon */
    private $orderUntil;

    public function __construct(Carbon $targetStart)
    {
        $timezone = self::timezone();
        $this->targetStart = $targetStart->copy()->timezone($timezone)->startOfMonth();
        $this->orderUntil = $this->targetStart->copy();
        $this->orderFrom = $this->targetStart->copy()->subMonths(6);
    }

    public static function parse(string $input, ?Carbon $now = null): self
    {
        $timezone = self::timezone();
        $now = ($now ?? Carbon::now($timezone))->timezone($timezone);
        $raw = strtolower(trim($input));
        $raw = ltrim($raw, '-');
        $raw = preg_replace('/^month=/', '', $raw);

        if ($raw === '') {
            throw new InvalidArgumentException('Bulan target wajib diisi. Contoh: --desember atau --12-2026');
        }

        if (preg_match('/^(\d{1,2})-(\d{4})$/', $raw, $match)) {
            $month = (int) $match[1];
            $year = (int) $match[2];
        } elseif (preg_match('/^(\d{1,2})$/', $raw, $match)) {
            $month = (int) $match[1];
            $year = (int) $now->year;
        } else {
            $alias = self::MONTH_ALIASES[$raw] ?? null;
            if ($alias === null) {
                throw new InvalidArgumentException(
                    'Format bulan tidak dikenali: ' . $input . '. Pakai --desember atau --12-2026'
                );
            }
            $month = $alias;
            $year = (int) $now->year;
        }

        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Bulan harus 1–12.');
        }

        return new self(Carbon::create($year, $month, 1, 0, 0, 0, $timezone));
    }

    public static function extractFromArgv(array $argv): ?string
    {
        $found = [];

        foreach ($argv as $arg) {
            if (!is_string($arg) || strpos($arg, '--') !== 0 || $arg === '--') {
                continue;
            }

            $token = substr($arg, 2);
            if (strpos($token, '=') !== false) {
                [$name, $value] = explode('=', $token, 2);
                if ($name === 'month' && trim($value) !== '') {
                    $found[] = $value;
                }
                continue;
            }

            if (preg_match('/^\d{1,2}-\d{4}$/', $token) || isset(self::MONTH_ALIASES[strtolower($token)])) {
                $found[] = $token;
            }
        }

        $found = array_values(array_unique($found));
        if (count($found) > 1) {
            throw new InvalidArgumentException('Pilih satu bulan saja. Ketemu: ' . implode(', ', $found));
        }

        return $found[0] ?? null;
    }

    public function targetStart(): Carbon
    {
        return $this->targetStart->copy();
    }

    public function orderFrom(): Carbon
    {
        return $this->orderFrom->copy();
    }

    public function orderUntil(): Carbon
    {
        return $this->orderUntil->copy();
    }

    public function month(): int
    {
        return (int) $this->targetStart->month;
    }

    public function year(): int
    {
        return (int) $this->targetStart->year;
    }

    public function label(): string
    {
        return $this->targetStart->locale('id')->translatedFormat('F Y');
    }

    public function token(): string
    {
        return sprintf('%02d-%d', $this->month(), $this->year());
    }

    public function monthName(): string
    {
        return self::MONTH_NAMES[$this->month()];
    }

    public function monthShort(): string
    {
        return self::MONTH_SHORT[$this->month()];
    }

    public function promoCode(): string
    {
        return self::formatPromoCode($this->monthName(), $this->monthShort(), $this->year());
    }

    public static function formatPromoCode(string $fullMonth, string $shortMonth, int $year): string
    {
        $full = 'Auto_' . $fullMonth . '_' . $year;
        if (strlen($full) <= self::PROMO_CODE_MAX) {
            return $full;
        }

        return 'Auto_' . $shortMonth . '_' . $year;
    }

    public function promoName(): string
    {
        return 'Auto ' . $this->monthName() . ' ' . $this->year();
    }

    private static function timezone(): string
    {
        try {
            return function_exists('config')
                ? (string) config('quotation_auto.timezone', 'Asia/Jakarta')
                : 'Asia/Jakarta';
        } catch (\Throwable $exception) {
            return 'Asia/Jakarta';
        }
    }
}
