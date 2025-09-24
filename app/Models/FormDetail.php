<?php
namespace App\Models;
use App\Models\Sector;

class FormDetail extends Sector
{
    protected $table = "form_detail";
    public $timestamps = false;
    protected $guarded = [];

    protected $connetions = "android_intilab";



    public function user()
    {
        return $this->hasOne(MasterKaryawan::class, 'id', 'user_id')
            ->select(['id', 'nama_lengkap', 'id_department', 'kode_qr', 'id_posision', 'alamat']);
    }

    public function atapproved_atasan_by()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approved_atasan_by', 'nama_lengkap') // Asumsi `atasan_by` berisi nama lengkap
            ->select(['id', 'nama_lengkap', 'kode_qr']);
    }
    public function atapproved_hrd_by()
    {
        return $this->belongsTo(MasterKaryawan::class, 'approved_atasan_by', 'nama_lengkap') // Asumsi `atasan_by` berisi nama lengkap
            ->select(['id', 'nama_lengkap', 'kode_qr']);
    }

    // Relasi fleksibel untuk kolom dinamis
    public function relatedUser($column, $field = 'name')
    {
        return $this->belongsTo(MasterKaryawan::class, $column, $field)
            ->select(['id', 'name', 'email', 'username', 'role']);
    }
}