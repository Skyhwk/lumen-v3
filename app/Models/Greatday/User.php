<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use App\Models\MasterKaryawan;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use UsesGreatdayAppsConnection;

    protected $table = 'users';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $fillable = [
        'username',
        'email',
        'password',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at',
        'is_active',
    ];

    public $timestamps = false;

    public function tokens()
    {
        return $this->hasMany(UserToken::class, 'user_id', 'user_id');
    }

    /** users.user_id = master_karyawan.id (skema Internal / greatday) */
    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'user_id');
    }
}
