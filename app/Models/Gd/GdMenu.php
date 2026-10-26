<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use Illuminate\Database\Eloquent\Model;

class GdMenu extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_menu';

    protected $guarded = [];

    public $timestamps = false;

    public static function getAllGrantedMenus($userId)
    {
        $permissions = GdPermission::getPermissionsByUserId($userId);

        return self::whereIn('name', collect($permissions)->pluck('menu'))->get();
    }
}
