<?php

namespace App\Services\directorApp;

class Permission
{
    protected $path;

    public function __construct()
    {
        $this->path = storage_path('app/data/directorApp/permissions.json');
        if (!file_exists($this->path)) file_put_contents($this->path, json_encode([]));
    }

    protected function load()
    {
        return json_decode(file_get_contents($this->path), true);
    }

    public function getPermissionsByUserId($userId)
    {
        $permissions = collect($this->load());
        $permissions = $permissions->firstWhere('userId', $userId);

        return $permissions['access'];
    }
}
