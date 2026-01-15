<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class TemplateBackground extends Sector
{
    protected $table = "template_background";
    public $timestamps = false;

    protected $guarded = [];

}