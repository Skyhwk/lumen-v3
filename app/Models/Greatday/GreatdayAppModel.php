<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

abstract class GreatdayAppModel extends Model
{
    use UsesGreatdayAppsConnection;

    public $timestamps = false;
}
