<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Lemburan extends Sector
{
    protected $table = 'lemburan';
    protected $guard = [];


    protected $fillable = [
        'no_document',
        'type_doc',
        'lemburan_id',
        'approve',
        'approve_by',
        'approve_at',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public $timestamps = false;
}