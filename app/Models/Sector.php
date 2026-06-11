<?php
namespace App\Models;

use App\Services\WsFinalApprovalService;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    protected static function booted()
    {
        static::saved(function (Model $model) {
            if ($model->wasChanged('lhps')) {
                WsFinalApprovalService::syncParameter($model);
            }
        });
    }

    public function setConnection($connection)
    {
        $this->connection = $connection;
        foreach ($this->getRelations() as $relation) {
            if (method_exists($relation, 'setConnection')) {
                $relation->setConnection($connection);
            }
        }
        return $this;
    }
}
