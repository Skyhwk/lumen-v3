<?php

namespace App\Models\Greatday\Concerns;

trait UsesGreatdayAppsConnection
{
    public function getConnectionName()
    {
        return config('greatday.apps_connection', 'intilab_apps');
    }
}
