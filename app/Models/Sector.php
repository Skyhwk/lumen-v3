<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
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