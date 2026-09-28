<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

class TemporaryRegister extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'temporary_register';

    protected $guarded = [];

    public $timestamps = false;
}
