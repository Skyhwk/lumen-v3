<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'notification';

    protected $guarded = ['id'];

    protected $casts = [
        'extra_data' => 'array',
    ];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
