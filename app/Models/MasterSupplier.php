<?php

namespace App\Models;

class MasterSupplier extends Sector
{
    protected $table = 'master_suppliers';

    protected $guarded = ['id'];

    public $timestamps = false;
}
