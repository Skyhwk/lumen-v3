<?php

namespace App\Models\Hr;

use Illuminate\Database\Eloquent\Model;

class HrApprovalStep extends Model
{
    public const STEP_ATASAN = 'atasan';

    public const STEP_HRD = 'hrd';

    public const STEP_FINANCE = 'finance';

    public const STATE_PENDING = 'pending';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_SKIPPED = 'skipped';

    protected $table = 'hr_approval_step';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(HrRequest::class, 'request_id');
    }
}
