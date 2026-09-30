<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'request_log';

    protected $guarded = [];

    public $timestamps = false;
}
