<?php
namespace App\Helpers;

use App\Models\Colorimetri;
use App\Models\Ftc;
use App\Models\FtcT;
use App\Models\Gravimetri;
use App\Models\Subkontrak;
use App\Models\Titrimetri;
use App\Models\WsValueAir;
use App\Models\Tftc;
use App\Models\Tftct;

class Fixing
{

    // ================================================= PERUBAHAN NO SAMPEL ==========================================================
    public static function KategoriModel($kategori): array
    {
        $models = [
            'Air' => [
                Colorimetri::class,
                Titrimetri::class,
                Gravimetri::class,
                Subkontrak::class,
                WsValueAir::class,
            ],
            // tambah kategori lain di sini
        ];

        return $models[$kategori] ?? [];
    }

    /**
     * Model yang FK-nya ada di WsValueAir
     * Key = nama kolom FK di tabel ws_value_airs
     */
    public static function WsValueAirRelasi(): array
    {
        return [
            'id_colorimetri' => Colorimetri::class,
            'id_titrimetri'  => Titrimetri::class,
            'id_gravimetri'  => Gravimetri::class,
            'id_subkontrak'  => Subkontrak::class,
        ];
    }

    /**
     * Model yang berlaku untuk SEMUA kategori
     * dengan behavior UPDATE (copy kolom not-null dari lama ke baru)
     */
    public static function GlobalUpdateModel(): array
    {
        return [
            Ftc::class,
            FtcT::class,
        ];
    }

    /**
     * Kolom yang di-skip saat copy dari lama ke baru
     */
    public static function SkipKolom(): array
    {
        return ['id', 'no_sample', 'is_active'];
    }

    // ================================================= END PERUBAHAN NO SAMPEL ==========================================================

}