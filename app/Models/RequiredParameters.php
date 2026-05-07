<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RequiredParameters extends Sector
{
    protected $table = 'required_parameters';
    public $timestamps = false;
    protected $guarded = [];
}
