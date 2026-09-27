<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;

/** Log absensi Greatday (selfie + geo), terpisah dari baris mesin RFID di `absensi`. */
class AbsensiAndroid extends Model
{
    use UsesGreatdayProduksiConnection;

    protected $table = 'absensi_mobile';

    protected $guarded = ['id'];

    public $timestamps = false;
}
