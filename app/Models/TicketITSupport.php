<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class TicketITSupport extends Sector{
    protected $table = 'ticket_it_support';
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