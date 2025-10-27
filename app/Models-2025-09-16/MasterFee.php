<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class MasterFee extends Sector{
    protected $table = 'master_fee';
    protected $guard = [];

    
    protected $fillable = [
        'tipe',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
        'deleted_at',
        'deleted_by',
        'previous_id',
        'is_active',
    ];

    public $timestamps = false;

    
}