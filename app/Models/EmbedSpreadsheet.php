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
        'source',
        'url_form',
        'type',
        'created_by',
        'updated_by',
        'deleted_by'
    ];

    protected $appends = ['url'];

    public function getUrlAttribute()
    {
        return $this->source ?: $this->url_form;
    }
}
