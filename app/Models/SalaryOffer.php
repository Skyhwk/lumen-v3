<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryOffer extends Model
{
    protected $table = 'sallary_offer';
    protected $guarded = [];

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
