<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use App\Models\MasterKaryawan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class UserToken extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'user_token';

    public $timestamps = false;

    protected $casts = [
        'is_expired' => 'boolean',
        'is_logged_in' => 'boolean',
        'expired' => 'datetime',
        'create_date' => 'datetime',
    ];

    protected $fillable = [
        'user_id',
        'token',
        'create_date',
        'expired',
        'is_logged_in',
        'is_expired',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'user_id');
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
