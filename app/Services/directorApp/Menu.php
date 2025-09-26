<?php

namespace App\Services\directorApp;

use App\Services\directorApp\Permission;

class Menu
{
    protected $path;
    protected $permissions;

    public function __construct(Permission $permissions)
    {
        $this->path = storage_path('app/data/directorApp/menus.json');
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));

        $this->permissions = $permissions;
    }

    protected function load()
    {
        return json_decode(file_get_contents($this->path), true);
    }

    public function getAllMenus()
    {
        return collect($this->load());
    }

    public function getAllGrantedMenus($userId)
    {
        return collect($this->load())->filter(function ($menu) use ($userId) {
            return collect($this->permissions->getPermissionsByUserId($userId))
                ->contains(function ($perm) use ($menu) {
                    return $perm['menu'] === $menu['name'];
                });
        });
    }
}
