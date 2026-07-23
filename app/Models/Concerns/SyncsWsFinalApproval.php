<?php

namespace App\Models\Concerns;

use App\Models\Builders\WsFinalApprovalBuilder;
use App\Services\WsFinalApprovalService;
use Illuminate\Database\Eloquent\Model;

trait SyncsWsFinalApproval
{
    public function newEloquentBuilder($query)
    {
        return new WsFinalApprovalBuilder($query);
    }

    protected static function bootSyncsWsFinalApproval()
    {
        static::saved(function (Model $model) {
            if ($model->wasChanged('lhps')) {
                WsFinalApprovalService::syncParameter($model);
            }
        });
    }
}
