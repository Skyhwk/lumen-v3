<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class FcmToken extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'fcm_token';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
