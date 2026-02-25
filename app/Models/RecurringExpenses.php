<?php
namespace App\Models;

use App\Models\Sector;

class RecurringExpenses extends Sector
{

    protected $table = 'recurring_expenses';

    public $timestamps = false;

    protected $guarded = [];


    public function details()
    {
        return $this->hasMany(RecurringDetails::class, 'recurring_expense_id', 'id');
    }

}
