<?php

namespace App\Models;

class ClaimFeeExternalApprovalLog extends Sector
{
    protected $table = 'claim_fee_external_approval_logs';

    public $timestamps = false;

    protected $guarded = [];

    public const ACTION_APPROVED = 'APPROVED';
    public const ACTION_REJECTED = 'REJECTED';

    public const STAGE_SPV = 'SPV';
    public const STAGE_MANAGER = 'MANAGER';
    public const STAGE_SENIOR_MANAGER = 'SENIOR_MANAGER';
}