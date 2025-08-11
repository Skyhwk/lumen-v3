<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class RequestLog extends Sector
{

    protected $table = 'request_log';

    protected $fillable = [
        'name_req', 
        'date_req', 
        'data_req', 
        'user_agent', 
        'result', 
        'path_info', 
        'ip', 
        'platform'
    ];

    public $timestamps = false;
}
