<?php

namespace App\Services;

use App\Models\customer\CsTicket;
use RuntimeException;

class CustomerServiceTicketNumberGenerator
{
    private const CHARSET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const LENGTH = 8;
    private const MAX_ATTEMPTS = 10;

    public static function generate(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = self::buildRandomCode();

            if (!CsTicket::where('ticket_no', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal menghasilkan nomor ticket unik.');
    }

    private static function buildRandomCode(): string
    {
        $chars = self::CHARSET;
        $max = strlen($chars) - 1;
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }
}
