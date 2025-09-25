<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterSubKategori extends Sector
{

    protected $table = 'master_sub_kategori';

    protected $fillable = [
        'nama_sub_kategori',
        'id_kategori',
        'nama_kategori',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $timestamps = false;

}
