<?php

namespace App\Models;

use App\Models\Sector;

class RecurringDetails extends Sector
{

    protected $table = 'recurring_details';

    public $timestamps = false;
    
    protected $guarded = [];

    public function header()
    {
        return $this->belongsTo(RecurringExpenses::class, 'recurring_expense_id', 'id');
    }
}
