<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class TicketSampling extends Sector{
    protected $table = 'ticket_sampling';
    protected $guarded = [];

    public $timestamps = false;

    // public function previous()
    // {
    //     return $this->belongsTo(YourModel::class, 'previous_id');
    // }

    // public function karyawan(){
    //     return $this->belongsTo(MasterKaryawan::class, 'karyawan_id', 'id');
    // }
}