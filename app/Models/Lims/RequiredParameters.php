<?php
namespace App\Models\Lims;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RequiredParameters extends Sector
{
    protected $connection = 'lims';

    protected $table = 'required_parameters';
    public $timestamps = false;
    protected $guarded = [];
}
