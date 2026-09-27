<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use UsesGreatdayProduksiConnection;

    protected $table = 'announcement';

    protected $guarded = ['id'];

    public $timestamps = false;
}
