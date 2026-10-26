<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use Illuminate\Database\Eloquent\Model;

class GdPermission extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_permission';

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
