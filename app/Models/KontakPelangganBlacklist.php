<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KontakPelangganBlacklist extends Sector
{

    protected $table = 'kontak_pelanggan_blacklist';

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

    public function pelanggan_blacklist()
    {
        return $this->belongsTo(MasterPelangganBlacklist::class, 'pelanggan_id');
    }
}
