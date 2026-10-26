<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

class TemporaryForgot extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'temporary_forgot';

    protected $guarded = [];

    public $timestamps = false;
}
