<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use App\Models\MasterKaryawan;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Model;

class GdUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    use UsesGdProduksiConnection;

    protected $table = 'gd_user';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $hidden = ['password', 'pin_user'];

    protected $guarded = [];

    public $timestamps = false;

    public function tokens()
    {
        return $this->hasMany(GdUserToken::class, 'karyawan_id', 'karyawan_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    }
}
