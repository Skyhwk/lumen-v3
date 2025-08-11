<?php
namespace App\Models;

use App\Models\Sector;

class Recruitment extends Sector
{

    protected $table = 'recruitment';

    public $timestamps = false;


    public function examps()
    {
        return $this->hasOne(RecruitmantExamp::class, 'kode_uniq', 'kode_uniq');
    }

    public function jabatan()
    {
        return $this->belongsTo(MasterJabatan::class, 'bagian_di_lamar', 'id');
    }

}
