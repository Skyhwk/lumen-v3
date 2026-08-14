<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateDataOffers extends Model
{
    protected $table = 'candidate_data_offers';
    protected $guarded = [];

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
