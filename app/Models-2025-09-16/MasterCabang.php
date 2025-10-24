<?php
namespace App\Models;

use App\Models\Sector;

class MasterCabang extends Sector
{
    protected $table = 'master_cabang';

    protected $fillable = [
        'kode_cabang',
        'nama_cabang',
        'alamat_cabang',
        'tlp_cabang',
        'created_by',
        'updated_by',
        'deleted_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public $timestamps = false;

    public function karyawans()
    {
        return $this->hasMany(MasterKaryawan::class, 'id_cabang');
    }
}
