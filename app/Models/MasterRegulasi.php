<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterRegulasi extends Sector
{

    protected $table = 'master_regulasi';

    protected $fillable = [
        'id_kategori',
        'nama_kategori',
        'peraturan',
        'deskripsi',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public $timestamps = false;

    public function bakumutu()
    {
        return $this->hasMany(MasterBakumutu::class, 'id_regulasi')->where('is_active', true);
    }

}