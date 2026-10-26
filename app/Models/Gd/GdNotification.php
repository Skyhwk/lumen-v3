<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use App\Models\MasterKaryawan;
use Illuminate\Database\Eloquent\Model;

class GdNotification extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_notification';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'extra_data' => 'array',
        'is_seen' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'user_id');
    }
}
