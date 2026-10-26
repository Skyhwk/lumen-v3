<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'menu';

    protected $guarded = [];

    public $timestamps = false;

    public static function getAllGrantedMenus($userId)
    {
        $permissions = Permission::getPermissionsByUserId($userId);

        return self::whereIn('name', collect($permissions)->pluck('menu'))->get();
    }
}
