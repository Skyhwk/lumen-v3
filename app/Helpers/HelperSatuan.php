<?php

namespace App\Helpers;

class HelperSatuan
{

    public static function udara($satuan)
    {
        $satuanIndexMap = [
            'kali/jam' => 18,
            "µg/m³" => 16,
            "µg/m3" => 16,
            "mg/m³" => 17,
            "mg/m³" => 17,
            "mg/m3" => 17,
            "BDS" => 15,
            "CFU/M²" => 14,
            "CFU/M2" => 14,
            "CFU/25cm²" => 13,
            "CFU/25cm2" => 13,
            "°C" => 12,
            "CFU/100 cm²" => 11,
            "CFU/100 cm2" => 11,
            "CFU/m²" => 10,
            "CFU/m2" => 10,
            "CFU/m³" => 9,
            "CFU/m3" => 9,
            "CFU/mᶟ" => 9,
            "m/s" => 8,
            "m/detik" => 8,
            "f/cc" => 7,
            "Ton/km²/Bulan" => 6,
            "Ton/km2/Bulan" => 6,
            "%" => 5,
            "ppb" => 4,
            "ppm" => 3,
            "mg/nm³" => 2,
            "mg/nm3" => 2,
            "μg/Nm³" => 1,
            "μg/Nm3" => 1,
            "µg/Nm³" => 1
        ];

        return $satuanIndexMap[$satuan] ?? null;
    }

    public static function emisi($satuan)
    {
        $satuanIndexMap = [
            "μg/Nm³" => "",
            "μg/Nm3" => "",

            "mg/nm³" => 1,
            "mg/nm3" => 1,
            "mg/Mm³" => 1,
            "mg/Nm3" => 1,
            "mg/Nm³" => 1,
            "mg/Nm³" => 1,

            "ppm"    => 2,
            "PPM" => 2,

            "ug/m3" => 3,
            "ug/m³" => 3,

            "mg/m3"  => 4,
            "mg/m³"  => 4,
            "mg/m³"  => 4,

            "%"      => 5,
            "°C"     => 6,
            "g/gmol" => 7,
            "m3/s"   => 8,
            "m/s"    => 9,
            "kg/tahun" => 10,
        ];

        return $satuanIndexMap[$satuan] ?? null;
    }
}
