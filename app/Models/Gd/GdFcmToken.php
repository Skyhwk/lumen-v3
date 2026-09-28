<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class GdFcmToken extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_fcm_token';

    protected $guarded = ['id'];

    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
