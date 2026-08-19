<?php
namespace App\Models;

use App\Models\Sector;

class MasterDivisi extends Sector
{

    protected $table = 'master_divisi';

    protected $fillable = [
        'kode_divisi',
        'nama_divisi',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at',
        'is_active'
    ];

    public $timestamps = false;

    public function karyawans()
    {
        return $this->hasMany(MasterKaryawan::class, 'id_department');
    }

    public function jabatan()
    {
        return $this->hasMany(MasterJabatan::class, 'id_divisi');
    }

    public function scopeActive($query)
    {
        return $query->where($this->getTable() . '.is_active', 1);
    }
}
