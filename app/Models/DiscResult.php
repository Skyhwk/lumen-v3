<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class DiscResult extends Sector
{
    protected $table = "disc_result";
    public $timestamps = false;

}