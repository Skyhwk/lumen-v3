<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class TicketRLHP extends Sector{
    protected $table = 'ticket_r_lhp';
    protected $guarded = [];

    public $timestamps = false;
    
    // protected $casts = [
    //     'perubahan_data'     => 'array',
    //     'perubahan_tanggal'  => 'array',
    //     'data_perusahaan'       => 'array',
    // ];

    // public function previous()
    // {
    //     return $this->belongsTo(YourModel::class, 'previous_id');
    // }

    // public function karyawan(){
    //     return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    // }
}