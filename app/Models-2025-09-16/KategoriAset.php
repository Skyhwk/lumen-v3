<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class KategoriAset extends Sector
{
    protected $table = 'kategori_aset';
    protected $guarded = [];
    public $timestamps = false;

    
}