<?php
namespace App\Models;

use App\Models\Sector;

class OfferingSalary extends Sector
{
    protected $table = "offering_salary";
    public $timestamps = false;
    protected $guarded = [];
}
