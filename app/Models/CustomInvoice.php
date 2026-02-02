<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Sector;

class CustomInvoice extends Sector
{
    protected $table = 'custom_invoice';
    protected $guarded = ['id'];


    public $timestamps = false;
}