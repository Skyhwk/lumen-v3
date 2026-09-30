<?php

namespace App\Models\Greatday;

use App\Models\Greatday\Concerns\UsesGreatdayAppsConnection;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use UsesGreatdayAppsConnection;

    protected $table = 'permission';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'access' => 'array',
    ];

    public static function getPermissionsByUserId($userId)
    {
        $permission = self::where('userId', $userId)->first();

        return $permission ? ($permission->access ?? []) : [];
    }
}
