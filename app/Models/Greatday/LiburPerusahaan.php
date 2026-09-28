<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;

class LiburPerusahaan extends Model
{
    use UsesGreatdayProduksiConnection;

    protected $table = 'libur_perusahaan';

    protected $guarded = ['id'];

    public $timestamps = false;
}
