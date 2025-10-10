<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Sector;

class MailList extends Sector
{
    use HasFactory;

    protected $table = 'mail_lists';

    public function schedule(){
        return $this->belongsTo('App\Models\MailSchedule', 'id', 'mail_id');
    }
}