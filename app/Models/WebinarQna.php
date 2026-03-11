<?php
namespace App\Models;

use App\Models\Sector;

class WebinarQna extends Sector
{
    protected $table = 'webinar_qna';
    protected $guarded = [];

    public $timestamps = false;
}