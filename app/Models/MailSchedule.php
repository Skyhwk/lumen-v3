<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Sector;

class MailSchedule extends Sector
{
    use HasFactory;

    protected $table = 'mail_schedules';

    public function project(){
        return $this->belongsTo('App\Models\MailList', 'mail_id', 'id');
    }
}