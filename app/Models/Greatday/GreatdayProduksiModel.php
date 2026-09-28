<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;

abstract class GreatdayProduksiModel extends Model
{
    use UsesGreatdayProduksiConnection;

    public $timestamps = false;
}
