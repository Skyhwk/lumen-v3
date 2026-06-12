<?php

namespace App\Models\Concerns;

use App\Models\WsValueAir;

trait SyncsWsValueAirFromChild
{
    protected static function bootSyncsWsValueAirFromChild()
    {
        static::saved(function ($model) {
            WsValueAir::pushChildFieldsToWsValueAir($model);
        });
    }
}
