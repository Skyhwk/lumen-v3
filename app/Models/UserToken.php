<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class UserToken extends Sector
{
    protected $table = 'user_token';

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'user_id', 'user_id');
    }

    public function akses()
    {
        return $this->hasOne(AksesMenu::class, 'user_id', 'user_id')->where('is_active', true);
    }

    public function webphone()
    {
        return $this->hasOneThrough(Webphone::class, MasterKaryawan::class, 'user_id', 'karyawan_id', 'user_id', 'id');
    }
}
