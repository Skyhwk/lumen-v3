<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;
use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;

class User extends Sector implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    protected $table = 'users';

    protected $hidden = [
        'password', 'remember_token',
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
        'is_active'
    ];

    public $timestamps = false;

    public function tokens()
    {
        return $this->hasMany(UserToken::class, 'user_id');
    }

    public function karyawan()
    {
        return $this->hasOne(MasterKaryawan::class, 'user_id');
    }

    public function akses()
    {
        return $this->hasOne(AksesMenu::class, 'user_id');
    }
}

