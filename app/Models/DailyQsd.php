<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyQsd extends Model
{
    protected $table = 'daily_qsd';
    protected $guarded = [];
    public $timestamps = false;

    public function invoice()
    {
        return $this->hasMany(Invoice::class, 'no_quotation', 'no_quotation')->with('recordPembayaran', 'recordWithdraw')->where('is_active', 1);
    }
}