<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class TicketRevisiQt extends Sector{
    protected $table = 'ticket_revisi_qt';
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