<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DatabaseDevice extends Sector
{
    protected $table = 'database_device';
    protected $guarded = [];
    public $timestamps = false;
}