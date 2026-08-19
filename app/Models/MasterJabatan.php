<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterJabatan extends Sector
{
    protected $table = 'master_jabatan';

    protected $fillable = [
        'kode_jabatan',
        'nama_jabatan',
        'id_divisi',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
        'deleted_by',
        'deleted_at',
        'is_active'
    ];

    public function divisi()
    {
        return $this->belongsTo(MasterDivisi::class, 'id_divisi');
    }

    public function karyawans()
    {
        return $this->hasMany(MasterKaryawan::class, 'id_jabatan');
    }

    public function scopeActive($query)
    {
        return $query->where($this->getTable() . '.is_active', 1);
    }

    public $timestamps = false;
}
