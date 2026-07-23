<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    public function getConnectionName()
    {
        if ($this->connection) {
            return $this->connection;
        }

        // Tentukan koneksi secara dinamis berdasarkan namespace
        if (strpos(static::class, 'App\\Models\\Lims\\') === 0) {
            return 'lims';
        }

        // Untuk model non-Lims, kembalikan koneksi default (apps) secara eksplisit
        return config('database.default', 'mysql');
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
