<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class Withdraw extends Sector{
    protected $table = 'withdraw';
    protected $guard = [];


    public $timestamps = false;
}