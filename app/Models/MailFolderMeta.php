<?php

namespace App\Models;

class MailFolderMeta extends Sector
{
    protected $table = 'mail_folder_meta';
    protected $guarded = [];
    public $timestamps = false;

    public function karyawan()
    {
        return $this->belongsTo(MasterKaryawan::class, 'id_karyawan', 'id');
    }
}
