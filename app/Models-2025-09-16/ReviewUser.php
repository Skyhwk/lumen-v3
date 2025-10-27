<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ReviewUser extends Sector
{
    protected $table = 'review_user';
    public $timestamps = false;
    protected $guarded = [];
}
