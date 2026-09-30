<?php

namespace App\Models\Gd;

use App\Models\Gd\Concerns\UsesGdProduksiConnection;
use Illuminate\Database\Eloquent\Model;

class GdMigrationMap extends Model
{
    use UsesGdProduksiConnection;

    protected $table = 'gd_migration_map';

    protected $guarded = ['id'];

    public $timestamps = false;
}
