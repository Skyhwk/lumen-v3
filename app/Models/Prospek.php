<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Sector;

class Prospek extends Sector
{
    use HasFactory;
    protected $table = 'prospek_customer';
}
