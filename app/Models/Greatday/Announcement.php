<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayProduksiConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;
    use UsesGreatdayProduksiConnection;

    protected $table = 'announcement';

    protected $guarded = ['id'];

    public $timestamps = false;
}
