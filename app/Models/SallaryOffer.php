<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SallaryOffer extends Model
{
    protected $table = 'sallary_offer';
    protected $guarded = [];

    protected $casts = [
        'sallary_offer_hrd'      => 'float',
        'sallary_offer_user'     => 'float',
        'sallary_offer_direktur' => 'float',
        'final_sallary'          => 'float',
    ];

    public function applicant()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }

    public function newRecruitment()
    {
        return $this->belongsTo(NewRecruitment::class, 'new_recruitment_id');
    }
}
