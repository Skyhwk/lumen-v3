<?php

namespace App\Services;

use Mpdf;

class RenderSlipGaji
{
    private $data_karyawan;
    private $periode;
    private $data_gaji;
    
    private static $instance;


    /**
     * File wajib di proteksi (view, edit) dengan proteksi 11223333 (ddmmyyyy) tanggal lahir
     * 
     * cara pomanggilan service
     * RenderSlipGaji::where('periode', '2024-01-01')
     * ->where('data_karyawan', (object)datakaryawan) (nik, nama, depoartmenet, posisi, start date)
     * ->where('data_gaji', '1234567890')
     * ->generate();
     */

    public static function where($field, $value)
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        switch ($field) {
            case 'periode':
                if (empty($value)) {
                    throw new \Exception('Periode is required');
                }
                self::$instance->periode = $value;
                break;
            case 'data_karyawan':
                if (empty($value)) {
                    throw new \Exception('Data karyawan is required');
                }
                self::$instance->data_karyawan = $value;
                break;
            case 'data_gaji':
                if (empty($value)) {
                    throw new \Exception('Data gaji is required');
                }
                self::$instance->data_gaji = $value;
                break;
            default:
                throw new \Exception('Invalid field');
        }
    }

    public function generate()
    {
        // code di sini

        //=============close=============
        self::$instance = null;
    }
    
}