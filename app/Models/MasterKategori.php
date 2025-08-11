<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterKategori extends Sector
{
    protected $table = 'master_kategori';
    protected $guarded = [];
    public $timestamps = false;

    public function subCategories()
    {
        return $this->hasMany(MasterSubKategori::class, 'id_kategori', 'id')
            ->where('is_active', true);
    }
}