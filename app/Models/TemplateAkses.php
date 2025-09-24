<?php

namespace App\Models;

use App\Models\Sector;

class TemplateAkses extends Sector
{
    protected $table = 'template_akses';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = [
        'nama_template', 
        'akses', 
        'userid', 
        'created_at', 
        'updated_at', 
        'deleted_at', 
        'updated_by', 
        'created_by', 
        'deleted_by', 
        'is_active'
    ];
}
