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
    public static function waktuPemaparan($waktu)
    {
        // Hapus dd() untuk production
        
        // Pastikan input adalah numerik
        if (!is_numeric($waktu)) {
            return '0 menit';
        }
        
        // Konversi ke float untuk menangani decimal
        $waktu = (float) $waktu;
        
        // Jika waktu negatif, kembalikan 0 menit
        if ($waktu < 0) {
            return '0 menit';
        }
        
        $jam = floor($waktu / 60);
        $menit = $waktu % 60;
        $hasil = '';
        
        if ($jam > 0) {
            $hasil .= $jam . ' jam';
        }
        
        if ($menit > 0) {
            $hasil .= ($jam > 0 ? ' ' : '') . $menit . ' menit';
        }

        return $hasil ?: '0 menit';
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
}