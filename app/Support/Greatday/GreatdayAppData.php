<?php

namespace App\Support\Greatday;

use App\Models\Gd\GdFcmToken;
use App\Models\Gd\GdMenu;
use App\Models\Gd\GdNotification;
use App\Models\Gd\GdPermission;
use App\Models\Greatday\FcmToken;
use App\Models\Greatday\Menu;
use App\Models\Greatday\Notification;
use App\Models\Greatday\Permission;

/**
 * Pilih sumber data app greatday: intilab_apps (legacy) atau gd_* produksi (M8).
 */
final class GreatdayAppData
{
    public static function usesProduksiAppData(): bool
    {
        return (bool) config('greatday.use_produksi_gd_app_data', false);
    }

    public static function notificationQuery()
    {
        return self::usesProduksiAppData()
            ? GdNotification::query()
            : Notification::query();
    }

    public static function fcmTokenQuery()
    {
        return self::usesProduksiAppData()
            ? GdFcmToken::query()
            : FcmToken::query();
    }

    public static function menusForUser(int $userId): array
    {
        $menus = self::usesProduksiAppData()
            ? GdMenu::getAllGrantedMenus($userId)
            : Menu::getAllGrantedMenus($userId);

        return collect($menus)->values()->all();
    }

    public static function permissionsForUser(int $userId): array
    {
        $permissions = self::usesProduksiAppData()
            ? GdPermission::getPermissionsByUserId($userId)
            : Permission::getPermissionsByUserId($userId);

        return is_array($permissions) ? array_values($permissions) : collect($permissions)->values()->all();
    }
}
