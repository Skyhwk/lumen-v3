<?php

namespace App\Models;

use App\Models\Sector;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmbedSpreadsheet extends Sector
{
    use SoftDeletes;

    protected $table = 'embed_spreadsheets';
    public $timestamps = true;

    protected $fillable = [
        'nama_formulir',
        'url',
        'type',
        'created_by',
        'updated_by',
        'deleted_by'
    ];
}
