<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ReleaseSystem extends Sector{
    protected $table = 'release_system';
    protected $guarded = [];
    public $timestamps = false;
}