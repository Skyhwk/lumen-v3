<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ArsipProgrammer extends Sector{
    protected $table = 'arsip_programmer';
    protected $guarded = [];


    public $timestamps = false;
}