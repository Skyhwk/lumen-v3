<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrPermissionDetail extends Model
{
    protected $table = 'hr_permission_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];
}
