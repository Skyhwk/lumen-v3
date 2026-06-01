<?php

namespace App\Services;

use RuntimeException;
use InvalidArgumentException;

class Crypto
{
    private const SLICE_VERSION = 'v1';

    public function encryptSlice(string $data): string
    {
        if ($data === '') {
            return $data;
        }

        $key = $this->getSliceKey();
        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException('Slice encryption failed');
        }

        return self::SLICE_VERSION . '.' . base64_encode($iv . $ciphertext);
    }

    public function decryptSlice(string $data, bool $validateTtl = true): string
    {
        if ($data === '') {
            return $data;
        }

        $prefix = self::SLICE_VERSION . '.';
        if (strpos($data, $prefix) !== 0) {
            throw new InvalidArgumentException('Unsupported slice format');
        }

        $raw = base64_decode(substr($data, strlen($prefix)), true);
        if ($raw === false || strlen($raw) < 17) {
            throw new RuntimeException('Invalid slice payload');
        }

        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $this->getSliceKey(), OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new RuntimeException('Slice decryption failed');
        }

        if ($validateTtl) {
            $this->validateSliceTimestamp($plaintext);
        }

        return $plaintext;
    }

    private function validateSliceTimestamp(string $plaintext): void
    {
        $payload = json_decode($plaintext, true);

        if (!is_array($payload) || !isset($payload['ts'])) {
            throw new RuntimeException('Invalid slice payload');
        }

        $ttl = (int) env('SLICE_TTL', 300);
        $age = abs(time() - (int) $payload['ts']);

        if ($age > $ttl) {
            throw new RuntimeException('Slice expired');
        }
    }

    private function getSliceKey(): string
    {
        $secret = env('SLICE_SECRET', 'orang kuat orang yang sabar');

        if ($secret === '') {
            throw new RuntimeException('SLICE_SECRET is not configured');
        }

        if (strpos($secret, 'base64:') === 0) {
            $key = base64_decode(substr($secret, 7), true);

            if ($key === false || strlen($key) !== 32) {
                throw new RuntimeException('SLICE_SECRET base64 value must decode to 32 bytes');
            }

            return $key;
        }

        return hash('sha256', $secret, true);
    }

    /**
     * Legacy character-substitution encryption.
     * Used for sip_password and other existing DB records — do not switch to AES without migration.
     */
    public function encrypt($data)
    {
        if ($data != '') {
            if (is_array(str_split($data))) {
                $convert = '';
                foreach (str_split($data) as $key => $value) {
                    if ($value == ' ') {
                        $value = "s_PPX1";
                    }
                    $convert .= $this->inids($value);
                }
                return $convert;
            }
        }
        return $data;
    }

    /**
     * Legacy character-substitution decryption.
     * Used for sip_password and other existing DB records — do not switch to AES without migration.
     */
    public function decrypt($data)
    {
        if ($data != '') {
            $data = explode('8', $data);
            $convert = '';
            foreach ($data as $value) {
                if ($value === '') {
                    continue;
                }
                $val = $this->inids($value . '8', 1);
                if ($val == 's_PPX1') {
                    $val = " ";
                }
                $convert .= $val;
            }
            return $convert;
        }
        return $data;
    }

    protected function inids($string, $decode = 0)
    {
        $data = array(
            'a' => 'ssp8',
            'b' => 's21s48',
            'c' => 'xopA8',
            'd' => 'poxik8',
            'e' => 'Tak8',
            'f' => 'MkNixy8',
            'g' => 'IdPN8',
            'h' => 'OtuYx8',
            'i' => 'OtiX8',
            'j' => 'Z23x8',
            'k' => 'Zaee8',
            'l' => 'Rx38',
            'm' => 'R418',
            'n' => 'CapR8',
            'o' => 'Mui8',
            'p' => 'DtBy8',
            'q' => 'YxBi8',
            'r' => 'BiBG8',
            's' => 'muxYb8',
            't' => 'MZx8',
            'u' => 'mnz8',
            'v' => 'mzn8',
            'w' => 'MnCC8',
            'x' => 'BnM8',
            'y' => 'BVc8',
            'z' => 'BBc8',
            'A' => 'AAxY8',
            'B' => 'IojX8',
            'C' => 'XFhG8',
            'D' => 'XH8',
            'E' => 'xG8',
            'F' => 'GGJj8',
            'G' => 'Dx8',
            'H' => 'PR8',
            'I' => 'ER8',
            'J' => 'losp8',
            'K' => 'Hgk8',
            'L' => 'Jh8',
            'M' => 'Oxlao8',
            'N' => 'OOyx8',
            'O' => 'o00xY8',
            'P' => '0xP18',
            'Q' => '0xP8',
            'R' => 'sd208',
            'S' => 'JS08',
            'T' => 'KC8',
            'U' => 'qYkW8',
            'V' => 'qqQw8',
            'W' => 'Yuxq8',
            'X' => 'UUixYY8',
            'Y' => 'WWppxY8',
            'Z' => 'pxWW8',
            '0' => 'iiiY8',
            '1' => 'dxUYY8',
            '2' => 'SxTy8',
            '3' => 'G98',
            '4' => 'YuuI8',
            '5' => 'xITY8',
            '6' => 'DSYC8',
            '7' => 'CS28',
            '8' => 'PCSR8',
            '9' => 'OOS8',
            's_PPX1' => 'S8',
            ',' => 'sadP8',
            '.' => 'xpsd198',
            '[' => 'DTxDTp8',
            ']' => 'OPOP18',
            '(' => 'PlIcq8',
            ')' => 'FPOSx8',
            '\'' => '4PxxX8',
            '"' => 'DSTe8',
            '\\' => 'KaMP8',
            '?' => 'XPOS8',
            ':' => 'DPs8',
            ';' => 'TE38',
            '{' => 'xYaD8',
            '}' => 'xXDD918',
        );

        if ($decode == 0) {
            return $data[$string] ?? $string;
        }

        $key = array_search($string, $data);
        return $key !== false ? $key : $string;
    }
}
