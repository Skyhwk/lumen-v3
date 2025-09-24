<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class ReviewRecruitment extends Sector
{
    protected $table = 'review_recruitment';
    public $timestamps = false;
    protected $guarded = [];
}
