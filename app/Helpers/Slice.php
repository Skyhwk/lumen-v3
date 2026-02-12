<?php

namespace App\Helpers;

class Slice{
    public static function makeSlice(string $controller, string $function): string
    {
        $data = [
            'controller' => $controller,
            'function' => $function,
        ];
        
        return self::encryptSlice(json_encode($data));
    }


    public static function makeDecrypt(string $data)
    {
        return self::decryptSlice($data);
    }

    private function encryptSlice(string $data): string
    {
        if (empty($data)) {
            return $data;
        }

        return array_reduce(str_split($data), function($carry, $char) {
            $value = ($char === ' ') ? 's_PPX1' : $char;
            return $carry . self::getEncryptionMap($value);
        }, '');
    }

    private function decryptSlice(string $data): string
    {
        if (empty($data)) {
            return $data;
        }

        return array_reduce(explode('8', $data), function($carry, $value) {
            if ($value === '') {
                return $carry;
            }
            $decrypted = self::getEncryptionMap($value . '8', true);
            return $carry . ($decrypted === 's_PPX1' ? ' ' : $decrypted);
        }, '');
    }

    private function getEncryptionMap(string $char, bool $decode = false): string
    {
        $map = [
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
            // ... (rest of your encryption map)
        ];

        if ($decode) {
            $result = array_search($char, $map);
            return $result !== false ? $result : $char;
        }

        return $map[$char] ?? $char;
    }
}


