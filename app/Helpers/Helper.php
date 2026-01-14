<?php

namespace App\Helpers;
use Illuminate\Support\Facades\DB;
class Helper
{
   public static function tanggal_indonesia($tanggal)
   {
        $bulan = array(
            1 => 'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        );
        if ($tanggal != '') {
            $var = explode('-', $tanggal);
            return $var[2] . ' ' . $bulan[(int) $var[1]] . ' ' . $var[0];
        } else {
            return '-';
        }
    }
    // public static function waktuPemaparan($waktu)
    // {
    //     // Hapus dd() untuk production
        
    //     // Pastikan input adalah numerik
    //     if (!is_numeric($waktu)) {
    //         return '0 menit';
    //     }
        
    //     // Konversi ke float untuk menangani decimal
    //     $waktu = (float) $waktu;
        
    //     // Jika waktu negatif, kembalikan 0 menit
    //     if ($waktu < 0) {
    //         return '0 menit';
    //     }
        
    //     $jam = floor($waktu / 60);
    //     $menit = $waktu % 60;
    //     $hasil = '';
        
    //     if ($jam > 0) {
    //         $hasil .= $jam . ' jam';
    //     }
        
    //     if ($menit > 0) {
    //         $hasil .= ($jam > 0 ? ' ' : '') . $menit . ' menit';
    //     }

    //     return $hasil ?: '0 menit';
    // }

    public static function waktuPemaparan($waktu)
    {
        if (!is_numeric($waktu)) {
            return '0 jam';
        }

        $waktu = (float) $waktu;

        if ($waktu <= 0) {
            return '0 jam';
        }

        // menit ke jam
        $jam = $waktu / 60;

        // bulatkan 2 desimal (opsional)
        $jam = round($jam, 2);

        return $jam . ' jam';
    }

    public static function generateUniqueCode($table, $column = 'kode_uniq', $length = 5)
    {   
        try {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $maxAttempts = 10;

            for ($i = 0; $i < $maxAttempts; $i++) {
                $token = '';
                for ($j = 0; $j < $length; $j++) {
                    $token .= $characters[random_int(0, strlen($characters) - 1)];
                }

                // Cek keunikan
                $exists = DB::table($table)
                    ->where($column, $token)
                    ->exists();

                if (!$exists) {
                    return $token;
                }
            }

            // Jika setelah 10x gagal, bisa kembalikan error atau null
            throw new \Exception('Gagal menghasilkan kode unik setelah beberapa percobaan.');
        } catch (\Throwable $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ]);
        }

    }

    /**
     * Recursively normalizes the keys of an array.
     *
     * This function converts all array keys to lowercase and removes spaces (' ') and hyphens ('-').
     * It traverses nested arrays to ensure all keys, regardless of depth, are standardized.
     *
     * @param array $data The input array whose keys need to be normalized.
     * @return array The array with all keys converted to lowercase and free of spaces and hyphens.
     *
     * @example
     * // Input:
     * // $input = [
     * //     'First Name' => 'John',
     * //     'Last-Name' => 'Doe',
     * //     'Contact Info' => [
     * //         'E Mail' => 'john.doe@example.com',
     * //         'Phone-Number' => '1234567890'
     * //     ]
     * // ];
     *
     * // Output of normalize_format_key($input):
     * // [
     * //     'firstname' => 'John',
     * //     'lastname' => 'Doe',
     * //     'contactinfo' => [
     * //         'email' => 'john.doe@example.com',
     * //         'phonenumber' => '1234567890'
     * //     ]
     * // ]
    */
    public static function normalize_format_key(array $data, bool $asObject = false) 
    {
        $normalize_data = [];
        foreach ($data as $key => $value) {
            // 1. Convert the key to a string (if not already) and apply normalization rules.
            //    Rules: convert to lowercase and remove spaces and hyphens.
            //$newKey = strtolower(str_replace([' ', '-'], '_', (string) $key));
            $tempKey = strtolower((string) $key);
            $replacements = [
                '°' => '',        // Hapus derajat
                '>' => '_gt_',    // ganti > jadi gt (greater than) atau bisa '_lebih_'
                '<' => '_lt_',    // ganti < jadi lt (less than) atau bisa '_kurang_'
                '=' => '_eq_',    // sama dengan
            ];
            $tempKey = strtr($tempKey, $replacements);
            $tempKey = preg_replace('/[^a-z0-9]+/', '_', $tempKey);
            $newKey = trim($tempKey, '_');
            // 2. Handle nested arrays recursively.
            if (is_array($value)) {
                $value = self::normalize_format_key($value,$asObject); // Use 'self::' or 'static::' for static method calls.
            }

            // 3. Assign the value to the new normalized key.
            $normalize_data[$newKey] = $value;
        }
        return $asObject ? (object) $normalize_data : $normalize_data;
    }
}