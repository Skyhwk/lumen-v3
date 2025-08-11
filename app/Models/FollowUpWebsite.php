<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class FollowUpWebsite extends Sector
{
    protected $table = 'followup_website';
    protected $guarded = [];
    public $timestamps = false;
}