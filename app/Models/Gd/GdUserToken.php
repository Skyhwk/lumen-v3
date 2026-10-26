<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GdUserToken extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_user_token';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'is_expired' => 'boolean',
        'is_logged_in' => 'boolean',
        'expired' => 'datetime',
        'create_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(GdUser::class, 'karyawan_id', 'karyawan_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    }

    public function isActive(): bool
    {
        if ($this->is_expired) {
            return false;
        }

        if ($this->expired && Carbon::now()->greaterThan($this->expired)) {
            return false;
        }

        return true;
    }
}
