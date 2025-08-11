<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class PicPelanggan extends Sector
{

    protected $table = 'pic_pelanggan';

    protected $fillable = [
        'pelanggan_id', 
        'type_pic', 
        'nama_pic', 
        'jabatan_pic', 
        'no_tlp_pic', 
        'wa_pic', 
        'email_pic', 
        'created_by', 
        'updated_by', 
        'deleted_by', 
        'is_active'
    ];

    public $timestamps = false;

    public function pelanggan()
    {
        return $this->belongsTo(MasterPelanggan::class, 'pelanggan_id');
    }
}
