<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;

class Crypto
{
    public function encrypt($data){
        if ($data != '') {
            if (is_array(str_split($data))) {
                $convert = '';
                foreach (str_split($data) as $key => $value) {
                    if ($value == ' ') $value = "s_PPX1";
                    $convert .= $this->inids($value);
                }
                return $convert;
            }
        }
        return $data;
    }

    public function decrypt($data){
        if ($data != '') {
            $data = explode('8', $data);
            $convert = '';
            foreach ($data as $value) {
                if ($value === '') continue;
                $val = $this->inids($value . '8', 1);
                if ($val == 's_PPX1') $val = " ";
                $convert .= $val;
            }
            return $convert;
        }
        return $data;
    }

    protected function inids($string, $decode = 0){
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
        } else {
            $key = array_search($string, $data);
            return $key !== false ? $key : $string;
        }
    }
}
