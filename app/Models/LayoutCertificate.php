<?php
namespace App\Models;

use App\Models\Sector;

class LayoutCertificate extends Sector
{
    protected $table = 'layout_certificates';
    protected $guarded = [];

    public $timestamps = false;
}