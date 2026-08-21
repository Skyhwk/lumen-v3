<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class AssessmentInternal extends Sector{
    protected $table = 'assessment_internal';
    protected $guard = [];
    public $timestamps = false;
}