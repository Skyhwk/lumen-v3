<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrEventReportDetail extends Model
{
    protected $table = 'hr_event_report_detail';

    protected $primaryKey = 'request_id';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    public function request()
    {
        return $this->belongsTo(HrRequest::class, 'request_id');
    }
}
