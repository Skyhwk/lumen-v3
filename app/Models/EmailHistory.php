<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class EmailHistory extends Sector
{
    protected $table = 'email_history';

    public $timestamps = false;

}
