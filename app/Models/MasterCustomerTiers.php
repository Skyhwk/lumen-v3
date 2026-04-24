<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class MasterCustomerTiers extends Model
{
    protected $table = 'master_customer_tiers';

    protected $guarder = [];

    public $timestamps = false;
}