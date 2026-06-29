<?php

namespace App\Models;

class MailListIndex extends Sector
{
    protected $table = 'mail_list_index';
    protected $guarded = [];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
