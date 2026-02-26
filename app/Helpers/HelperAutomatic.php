<?php
namespace App\Helpers;

use App\Models\Colorimetri;
use App\Models\DebuPersonalHeader;
use App\Models\DustFallHeader;
use App\Models\EmisiCerobongHeader;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\Titrimetri;

class HelperAutomatic
{
    public static function getModelsByKategori($id_kategori)
    {
        $kategoriMap = [

            1 => [ // AIR

                'colorimetri' => [
                    'model'          => Colorimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

                'titrimetri'  => [
                    'model'          => Titrimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

                'gravimetri'  => [
                    'model'          => Gravimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

            ],

            4 => [ // UDARA
                'lingkungan'    => [
                    'model'          => LingkunganHeader::class,
                    'approved_field' => 'is_approved',
                ],
                'debu_personal' => [
                    'model'          => DebuPersonalHeader::class,
                    'approved_field' => 'is_approved',
                ],
                'dust_fall'     => [
                    'model'          => DustFallHeader::class,
                    'approved_field' => 'is_approved',
                ],
            ],

            5 => [ // EMISI
                'emisi_cerobong' => [
                    'model'          => EmisiCerobongHeader::class,
                    'approved_field' => 'is_approved',
                ],
            ],

            6 => [ // PADATAN

                'colorimetri' => [
                    'model'          => Colorimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

                'titrimetri'  => [
                    'model'          => Titrimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

                'gravimetri'  => [
                    'model'          => Gravimetri::class,
                    'approved_field' => 'is_approved',
                    'extra_where'    => ['is_total' => false],
                ],

            ],
        ];

        return $kategoriMap[$id_kategori] ?? [];
    }

    public static function getParameterDirect(): array
    {
        return [
            1 => [ // AIR
                'Bau', 'Angka Bau', 'Angka Bau (NA)', 'AOX',
                'Kekeruhan', 'Kekeruhan (APHA-B-23-NA)', 'Kekeruhan (APHA-B-23)',
                'Kekeruhan (IKM-SP-NA)', 'Kekeruhan (IKM-SP)',
                'TDS', 'TDS (APHA-C-23-NA)', 'TDS (APHA-C-23)',
                'TDS (IKM-KM-NA)', 'TDS (IKM-KM)',
                'DO', 'DO (C-03-NA)', 'DO (C-03)', 'DO (G-03-NA)', 'DO (G-03)',
                'DHL', 'DTL'
            ],
        ];
    }
}
