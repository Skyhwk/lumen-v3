<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrConsultationDetail extends Model
{
    protected $table = 'hr_consultation_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    public function request()
    {
        return $this->belongsTo(HrRequest::class, 'request_id');
    }
}
