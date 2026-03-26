<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KontakPelanggan extends Sector
{

    protected $table = 'kontak_pelanggan';

    protected $fillable = [
        'pelanggan_id', 
        'no_tlp_perusahaan', 
        'email_perusahaan', 
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

    public function logWebphone()
    {
        return $this->hasMany(LogWebphone::class, 'number', 'no_tlp_perusahaan');
    }
}
